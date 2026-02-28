<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\RestaurantReservationSlot;
use App\Enums\ReservationStatus;
use App\Mail\ReservationCreatedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Mail;


/**
 * 訂位領域服務
 * - 名額控管：採用「DB 強一致 + Redis 短鎖」
 *   1) Redis 只用於短時間原子鎖（避免高併發撞同一時段）
 *   2) 於資料庫交易中 lockForUpdate 對應之 slot 列，檢查/遞增 reserved；
 *      取消時則遞減 reserved，確保不會超賣或少還。
 * - 可用量查詢：以餐廳 table_buckets 為 capacity，上述 slot.reserved 為已訂數，
 *   兩者相減得到 available。
 *
 * 防超買（Oversell）設計重點：
 * - Redis setnx 先拿到「短鎖」（餐廳/日期/時段）以降低爭用範圍。
 * - 交易內對 slot 行鎖 lockForUpdate，再檢查 reserved < capacity 才允許 +1。
 * - slot 設計唯一索引，避免併發下產生重複列。
 *
 * 防超還（Over-return）設計重點：
 * - 僅對 CONFIRMED 訂位做還位；
 * - 交易內行鎖 + reserved>0 才遞減，避免負數。
 */
class ReservationService
{
    /**
     * 產生 Redis 短鎖用的 key
     * - 以餐廳/日期/時段為單位鎖住臨界區，降低 DB 行鎖競爭
     */
    private function lockKey(int $restaurantId, string $date, string $timeslot): string
    {
        // 以餐廳/日期/時段為粒度鎖定臨界區（不含人數桶，較保守）
        return sprintf('lock:resv:%d:%s:%s', $restaurantId, $date, $timeslot);
    }

    /**
     * 建立訂位（強一致名額控管）
     * - 流程：
     *   1) 以 Redis setnx 取得短鎖，避免大量併發撞同 slot；
     *   2) 進 DB 交易，lockForUpdate 取得/建立 slot 列；
     *   3) 檢查 slot.reserved < capacity 後，寫入 reservation 並 slot.reserved +1；
     *   4) 交易成功後寄送通知信；
     *   5) finally 解鎖（Redis 刪除短鎖）。
     */
    public function book(int $userId, int $restaurantId, string $date, string $timeslot, int $partySize, array $guestEmails = []): array
    {
        // 1) 取得餐廳可用量設定（buckets）
        $restaurant = Restaurant::findOrFail($restaurantId);
        $buckets = (array) ($restaurant->table_buckets ?? []);
        $capacity = (int) ($buckets[(string) $partySize] ?? 0);
        if ($capacity <= 0) {
            throw new \InvalidArgumentException('no_capacity_for_party_size');
        }

        // 2) 基礎防呆：同餐廳已有有效訂位時不允許再次下單（可依需求調整）
        $exists = Reservation::where('restaurant_id', $restaurantId)
            ->where('user_id', $userId)
            ->where('status', ReservationStatus::CONFIRMED)
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException('already_reserved_this_restaurant');
        }

        $lockKey = $this->lockKey($restaurantId, $date, $timeslot);

        // 3) 取得 Redis 短鎖（避免併發同時進入臨界區）
        //    - setnx 成功代表取得鎖，隨後設定短期過期避免死鎖
        $lock = Redis::setnx($lockKey, 1);
        if (!$lock) {
            throw new \RuntimeException('try_again');
        }
        Redis::expire($lockKey, 15); // 短期鎖（秒），避免競爭視窗

        try {
            // 為使用者回傳的訂位代碼與匿名查詢用短 token
            $code = app(JwtService::class)->uuid();
            $short = bin2hex(random_bytes(8));

            // 4) 交易內使用 DB 行鎖確保一致性：
            //    - 取出/建立 slot 計數列並 lockForUpdate
            //    - 確認 reserved < capacity 後才建立訂位並 +1
            DB::transaction(function () use ($userId, $restaurantId, $date, $timeslot, $partySize, $code, $short, $guestEmails, $capacity) {
                // 強一致：鎖定/建立 slot 計數列
                $slot = RestaurantReservationSlot::where([
                    'restaurant_id' => $restaurantId,
                    'reserve_date'  => $date,
                    'timeslot'      => $timeslot,
                    'party_size'    => $partySize,
                ])->lockForUpdate()->first();
                if (!$slot) {
                    $slot = new RestaurantReservationSlot([
                        'restaurant_id' => $restaurantId,
                        'reserve_date'  => $date,
                        'timeslot'      => $timeslot,
                        'party_size'    => $partySize,
                        'reserved'      => 0,
                    ]);
                }
                if ($slot->reserved >= $capacity) {
                    throw new \InvalidArgumentException('sold_out');
                }
                // 3) 建立訂位 + 名額遞增
                $res = Reservation::create([
                    'restaurant_id' => $restaurantId,
                    'user_id'       => $userId,
                    'reserve_date'  => $date,
                    'timeslot'      => $timeslot,
                    'party_size'    => $partySize,
                    'status'        => ReservationStatus::CONFIRMED,
                    'code'          => $code,
                    'short_token'   => $short,
                ]);
                $slot->reserved += 1;
                $slot->save();
                foreach ($guestEmails as $email) {
                    ReservationGuest::create(['reservation_id' => $res->id, 'email' => $email]);
                }
            });

            // 5) 寄信（非同步 queue）：需 QUEUE_CONNECTION 與 mailer 設定
            $shortLink = url('/api/reservations/short/'.$short);
            $restaurantName = $restaurant->name;
            $mail = new ReservationCreatedMail($restaurantName, $date, $timeslot, $partySize, $shortLink);

            $recipients = [];
            $userEmail = User::where('id', $userId)->value('email');
            if ($userEmail) { $recipients[] = $userEmail; }
            foreach ($guestEmails as $g) {
                if ($g) { $recipients[] = $g; }
            }
            $recipients = array_values(array_unique($recipients));
            foreach ($recipients as $to) {
                try { Mail::to($to)->queue(clone $mail); } catch (\Throwable $ignore) {}
            }

            return [
                'code'       => $code,
                'shortToken' => $short,
            ];
        } catch (\Throwable $e) {
            // 交易失敗會整體回滾（包含 reserved 變動與 reservation 建立），不需額外處理
            throw $e;
        } finally {
            // 6) 釋放短鎖（確保最終一定解鎖）
            Redis::del($lockKey);
        }
    }

    /**
     * 取消單筆訂位（強一致還回名額）
     * - 在同一交易中，將 reservation 改 CANCELLED，並將 slot.reserved-1。
     */
    public function cancel(int $userId, int $reservationId): void
    {
        $res = Reservation::where('id', $reservationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // 僅對已確認的訂位執行取消（冪等：重複取消不動作）
        if ($res->status !== ReservationStatus::CONFIRMED) return;

        DB::transaction(function () use ($res) {
            $res->update(['status' => ReservationStatus::CANCELLED]);
            $slot = RestaurantReservationSlot::where([
                'restaurant_id' => $res->restaurant_id,
                'reserve_date'  => $res->reserve_date,
                'timeslot'      => $res->timeslot,
                'party_size'    => $res->party_size,
            ])->lockForUpdate()->first();
            // 強一致還位：行鎖 + reserved>0 才遞減，避免負數
            if ($slot && $slot->reserved > 0) {
                $slot->reserved -= 1;
                $slot->save();
            }
        });
    }

    /**
     * 依訂位代碼查詢訂位（匿名查詢）
     */
    public function findByCode(string $code): ?Reservation
    {
        return Reservation::where('code', $code)->first();
    }

    /**
     * 依短連結 token 查詢訂位（匿名查詢）
     */
    public function findByShortToken(string $token): ?Reservation
    {
        return Reservation::where('short_token', $token)->first();
    }

    /**
     * 取得使用者訂位列表
     */
    public function listByUser(int $userId)
    {
        return Reservation::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get(['id','restaurant_id as restaurantId','reserve_date as date','timeslot','party_size as partySize','status']);
    }

    /**
     * 修改使用者訂位時段：
     * - 若訂位仍為有效（CONFIRMED 且未過期）則拒絕修改。
     */
    public function updateTimeslotForUser(int $userId, int $reservationId, string $start, string $end): Reservation
    {
        $reservation = Reservation::where('id', $reservationId)
            ->where('user_id', $userId)
            ->first();
        if (!$reservation) {
            throw new \InvalidArgumentException('notFound');
        }

        $currentTimeslot = (string) $reservation->timeslot;
        $endStr = null;
        if (str_contains($currentTimeslot, '-')) {
            [, $endStr] = explode('-', $currentTimeslot, 2);
        }
        $now = now();
        $endAt = $endStr
            ? now()->parse($reservation->reserve_date.' '.$endStr)
            : now()->parse($reservation->reserve_date.' 23:59');
        $isActive = ($reservation->status === ReservationStatus::CONFIRMED) && $now->lte($endAt);
        if ($isActive) {
            throw new \InvalidArgumentException('cannotModifyTimeslotActive');
        }

        $reservation->update(['timeslot' => $start.'-'.$end]);
        return $reservation->refresh();
    }

    /**
     * 查詢單一人數桶的可用量（capacity/reserved/available）
     * - capacity 來自餐廳 table_buckets
     * - reserved 來自 restaurant_reservation_slots.reserved
     * 回傳：[{ start, end, capacity, reserved, available }]
     */
    public function availability(int $restaurantId, string $date, int $partySize): array
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        $timeslots = (array) ($restaurant->timeslots ?? []);
        $buckets   = (array) ($restaurant->table_buckets ?? []);
        $capacity  = (int) ($buckets[(string)$partySize] ?? 0);

        $rows = [];
        foreach ($timeslots as $slot) {
            // 僅支援物件 {start,end}
            $start = (string) ($slot['start'] ?? '');
            $end   = (string) ($slot['end'] ?? '');
            if ($start === '' || $end === '') continue;
            $label = "$start-$end";

            $slot = RestaurantReservationSlot::where([
                'restaurant_id' => $restaurantId,
                'reserve_date'  => $date,
                'timeslot'      => $label,
                'party_size'    => $partySize,
            ])->first();
            $reserved = (int) ($slot->reserved ?? 0);
            $available = max($capacity - $reserved, 0);
            $rows[] = [
                'start'     => $start,
                'end'       => $end,
                'capacity'  => $capacity,
                'reserved'  => $reserved,
                'available' => $available,
            ];
        }
        return $rows;
    }

    /**
     * 取得指定餐廳在某日期的全人數桶可用量（每個 timeslot 下各 partySize 的 capacity/reserved/available）。
     */
    public function availabilityDetail(int $restaurantId, string $date): array
    {
        $restaurant = Restaurant::findOrFail($restaurantId);
        $timeslots = (array) ($restaurant->timeslots ?? []);
        $buckets   = (array) ($restaurant->table_buckets ?? []);

        // 以 table_buckets 的 key 作為可訂人數集合
        $sizes = array_keys($buckets);
        sort($sizes, SORT_NUMERIC);

        $rows = [];
        foreach ($timeslots as $slot) {
            // 僅支援物件 {start,end}
            $start = (string) ($slot['start'] ?? '');
            $end   = (string) ($slot['end'] ?? '');
            if ($start === '' || $end === '') continue;
            $label = "$start-$end";

            $byPartySize = [];
            $totCap = $totRes = $totAvail = 0;
            foreach ($sizes as $size) {
                $cap = (int) ($buckets[(string)$size] ?? 0);
                $slot = RestaurantReservationSlot::where([
                    'restaurant_id' => $restaurantId,
                    'reserve_date'  => $date,
                    'timeslot'      => $label,
                    'party_size'    => (int)$size,
                ])->first();
                $res = (int) ($slot->reserved ?? 0);
                $avail = max($cap - $res, 0);
                $byPartySize[] = [
                    'size'      => (int) $size,
                    'capacity'  => $cap,
                    'reserved'  => $res,
                    'available' => $avail,
                ];
                $totCap += $cap; $totRes += $res; $totAvail += $avail;
            }

            $rows[] = [
                'start'     => $start,
                'end'       => $end,
                'byPartySize' => $byPartySize,
                'totals'    => [
                    'capacity'  => $totCap,
                    'reserved'  => $totRes,
                    'available' => $totAvail,
                ],
            ];
        }
        return $rows;
    }

    /**
     * 餐廳指定日期（可選 timeslot）的訂位彙總與清單（管理用途）。
     * - summary: 依 timeslot + party_size 聚合 count
     * - items:   簡要清單（不含敏感資訊）
     */
    public function reservationsOverview(int $restaurantId, string $date, ?string $timeslot = null): array
    {
        $base = Reservation::query()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('reserve_date', $date)
            ->where('status', ReservationStatus::CONFIRMED->value);

        if ($timeslot) {
            $base->where('timeslot', $timeslot);
        }

        $items = (clone $base)
            ->orderBy('timeslot')
            ->orderBy('party_size')
            ->get(['id','user_id','reserve_date','timeslot','party_size','status']);

        $summary = (clone $base)
            ->selectRaw('timeslot, party_size, COUNT(*) as count')
            ->groupBy('timeslot','party_size')
            ->orderBy('timeslot')
            ->orderBy('party_size')
            ->get();

        return [
            'summary' => $summary,
            'items'   => $items,
        ];
    }
}
