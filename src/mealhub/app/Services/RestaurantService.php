<?php

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantAdmin;
use App\Models\User;
use App\Models\Reservation;
use App\Enums\ReservationStatus;
use App\Enums\RestaurantStatus;

class RestaurantService
{
    /**
     * 取得餐廳列表（支援名稱/狀態篩選 + 分頁）
     * - filters: ['name' => string|null, 'status' => string|null, 'page' => int, 'perPage' => int]
     * - 回傳：['items' => Collection<Restaurant>, 'meta' => array]
     */
    public function list(array $filters): array
    {
        $page    = max(1, (int)($filters['page'] ?? 1));
        $perPage = (int)($filters['perPage'] ?? 10);
        $perPage = min(max($perPage, 1), 100);

        $query = Restaurant::query()
            ->when(!empty($filters['name']), function ($query) use ($filters) {
                $query->where('name', 'ILIKE', '%'.trim($filters['name']).'%');
            })
            ->when(!empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderByDesc('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'items' => $paginator->items(),
            'meta'  => [
                'page'     => $paginator->currentPage(),
                'perPage'  => $paginator->perPage(),
                'total'    => $paginator->total(),
                'lastPage' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * 建立餐廳並把建立者設為管理員
     */
    public function create(array $data, int $creatorId): Restaurant
    {
        $restaurant = Restaurant::create([
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'address'       => $data['address'] ?? null,
            'note'          => $data['note'] ?? null,
            'timeslots'     => $data['timeslots'] ?? null,
            'table_buckets' => $data['tableBuckets'] ?? null,
            'status'        => RestaurantStatus::ACTIVE,
        ]);

        RestaurantAdmin::create([
            'restaurant_id' => $restaurant->id,
            'user_id'       => $creatorId,
            'role'          => 'admin',
        ]);

        return $restaurant;
    }

    /**
     * 新增管理員（需現有管理員權限）
     */
    public function addAdmin(int $restaurantId, int $authUserId, int $targetUserId): void
    {
        $this->assertAdminOrFail($restaurantId, $authUserId);
        if (!User::where('id', $targetUserId)->exists()) {
            throw new \InvalidArgumentException('notFound');
        }
        $exists = RestaurantAdmin::where('restaurant_id', $restaurantId)
            ->where('user_id', $targetUserId)->exists();
        if (!$exists) {
            RestaurantAdmin::create([
                'restaurant_id' => $restaurantId,
                'user_id'       => $targetUserId,
                'role'          => 'admin',
            ]);
        }
    }

    /**
     * 移除管理員（不可移除自己，且需保留至少一位管理員）
     */
    public function removeAdmin(int $restaurantId, int $authUserId, int $targetUserId): void
    {
        $this->assertAdminOrFail($restaurantId, $authUserId);
        if ($targetUserId === $authUserId) {
            throw new \InvalidArgumentException('forbidden');
        }
        $adminCount = RestaurantAdmin::where('restaurant_id', $restaurantId)->count();
        $isTarget   = RestaurantAdmin::where('restaurant_id', $restaurantId)
            ->where('user_id', $targetUserId)->exists();
        if ($isTarget && $adminCount <= 1) {
            throw new \InvalidArgumentException('forbidden');
        }
        if ($isTarget) {
            RestaurantAdmin::where('restaurant_id', $restaurantId)
                ->where('user_id', $targetUserId)->delete();
        }
    }

    /**
     * 取得餐廳管理員列表（需管理員權限）
     */
    public function listAdmins(int $restaurantId, int $authUserId)
    {
        $this->assertAdminOrFail($restaurantId, $authUserId);
        $userIds = RestaurantAdmin::where('restaurant_id', $restaurantId)
            ->pluck('user_id');
        return User::whereIn('id', $userIds)->get();
    }

    /**
     * 更新餐廳資料（名稱/描述/地址/備註/桌數桶 timeslots 等）
     * - 若包含 timeslots，會套用 updateTimeslots 的限制（不可移除仍有效的時段）
     */
    public function updateDetails(int $restaurantId, int $authUserId, array $data)
    {
        $this->assertAdminOrFail($restaurantId, $authUserId);
        $restaurant = Restaurant::findOrFail($restaurantId);

        // timeslots 先處理（若提供）
        if (array_key_exists('timeslots', $data)) {
            $this->updateTimeslots($restaurantId, $authUserId, (array) $data['timeslots']);
            unset($data['timeslots']);
            // reload latest
            $restaurant->refresh();
        }

        $payload = [];
        if (array_key_exists('name', $data))          $payload['name'] = $data['name'];
        if (array_key_exists('description', $data))   $payload['description'] = $data['description'];
        if (array_key_exists('address', $data))       $payload['address'] = $data['address'];
        if (array_key_exists('note', $data))          $payload['note'] = $data['note'];
        if (array_key_exists('tableBuckets', $data))  $payload['table_buckets'] = $data['tableBuckets'];

        if (!empty($payload)) {
            $restaurant->update($payload);
        }

        return $restaurant;
    }

    /**
     * 取得單一餐廳
     */
    public function getById(int $restaurantId): Restaurant
    {
        return Restaurant::findOrFail($restaurantId);
    }

    /**
     * 更新餐廳可預約時段（start/end 陣列）。
     * 限制：若欲移除的時段目前存在有效訂位（CONFIRMED 且未過期），則拒絕。
     */
    public function updateTimeslots(int $restaurantId, int $authUserId, array $timeslots): void
    {
        $this->assertAdminOrFail($restaurantId, $authUserId);

        $restaurant = Restaurant::findOrFail($restaurantId);

        $current = (array) ($restaurant->timeslots ?? []);
        $currentLabels = [];
        foreach ($current as $slot) {
            if (is_array($slot)) {
                $s = (string) ($slot['start'] ?? '');
                $e = (string) ($slot['end'] ?? '');
                if ($s !== '' && $e !== '') $currentLabels[] = "$s-$e";
            } else {
                $currentLabels[] = (string) $slot;
            }
        }

        $newLabels = [];
        foreach ($timeslots as $slot) {
            $s = (string) ($slot['start'] ?? '');
            $e = (string) ($slot['end'] ?? '');
            if ($s !== '' && $e !== '') $newLabels[] = "$s-$e";
        }

        $toRemove = array_values(array_diff($currentLabels, $newLabels));

        if (!empty($toRemove)) {
            // 查詢有效訂位：該餐廳、狀態 CONFIRMED、timeslot 在移除清單、且尚未過期
            $today = now()->toDateString();
            $candidates = Reservation::where('restaurant_id', $restaurantId)
                ->where('status', ReservationStatus::CONFIRMED)
                ->whereIn('timeslot', $toRemove)
                ->whereDate('reserve_date', '>=', $today)
                ->get(['reserve_date','timeslot']);

            $activeExists = false;
            $now = now();
            foreach ($candidates as $r) {
                $label = (string) $r->timeslot;
                $end = null;
                if (str_contains($label, '-')) {
                    [, $end] = explode('-', $label, 2);
                }
                $endAt = $end ? now()->parse($r->reserve_date.' '.$end) : now()->parse($r->reserve_date.' 23:59');
                if ($now->lte($endAt)) { $activeExists = true; break; }
            }

            if ($activeExists) {
                throw new \InvalidArgumentException('cannotModifyTimeslotsActive');
            }
        }

        $restaurant->update(['timeslots' => $timeslots]);
    }

    /**
     * 確認使用者是否為該餐廳管理員，否則拋出 forbidden
     */
    private function assertAdminOrFail(int $restaurantId, int $userId): void
    {
        $isAdmin = RestaurantAdmin::where('restaurant_id', $restaurantId)
            ->where('user_id', $userId)
            ->exists();
        if (!$isAdmin) {
            throw new \InvalidArgumentException('forbidden');
        }
    }
}
