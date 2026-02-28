<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use App\Helper\Status;
use App\Http\Requests\ReservationCreateRequest;
use App\Http\Requests\ReservationCancelRequest;
use App\Http\Requests\ReservationUpdateRequest;
use App\Services\ReservationService;
use Illuminate\Http\Request;

/**
 * 訂位 API 控制器
 * - 建立/查詢/取消訂位等操作
 */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $service) {}

    /** 建立訂位（需要登入） */
    public function create(ReservationCreateRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $data = $request->validated();
        try {
            $timeslot = $data['start'].'-'.$data['end'];
            $result = $this->service->book(
                (int) $userId,
                (int) $data['restaurantId'],
                $data['date'],
                $timeslot,
                (int) $data['partySize'],
                $data['guestEmails'] ?? []
            );
            return ApiResponse::success($result);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        } catch (\Throwable $e) {
            return ApiResponse::error(Status::FAILURE, 'failure');
        }
    }

    /** 匿名以 code 查詢訂位 */
    public function showByCode(string $code)
    {
        $res = $this->service->findByCode($code);
        if (!$res) return ApiResponse::error(Status::FAILURE, 'notFound');
        return ApiResponse::success([
            'id' => $res->id,
            'restaurantId' => $res->restaurant_id,
            'date' => $res->reserve_date,
            'timeslot' => $res->timeslot,
            'partySize' => $res->party_size,
            'status' => $res->status,
        ]);
    }

    /** 匿名以短 token 查詢訂位 */
    public function showByShort(string $token)
    {
        $res = $this->service->findByShortToken($token);
        if (!$res) return ApiResponse::error(Status::FAILURE, 'notFound');
        return ApiResponse::success([
            'id' => $res->id,
            'restaurantId' => $res->restaurant_id,
            'date' => $res->reserve_date,
            'timeslot' => $res->timeslot,
            'partySize' => $res->party_size,
            'status' => $res->status,
        ]);
    }

    /** 取消單筆訂位（需要登入） */
    public function cancel(ReservationCancelRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $data = $request->validated();
        try {
            $this->service->cancel((int) $userId, (int) $data['reservationId']);
            return ApiResponse::success(['cancelled' => 1]);
        } catch (\Throwable $e) {
            return ApiResponse::error(Status::FAILURE, 'failure');
        }
    }
    /** 我的訂位列表（需要登入） */
    public function my(Request $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $items = $this->service->listByUser((int) $userId);
        return ApiResponse::success($items);
    }

    /**
     * 修改訂位時段：若該訂位仍為有效（未取消且未過期），則禁止修改。
     * 有效 = status=CONFIRMED 且 現在時間 <= (reserve_date + timeslot end)
     */
    public function update(ReservationUpdateRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $data = $request->validated();
        try {
            $res = $this->service->updateTimeslotForUser(
                (int) $userId,
                (int) $data['reservationId'],
                $data['start'],
                $data['end']
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }

        return ApiResponse::success([
            'id' => $res->id,
            'restaurantId' => $res->restaurant_id,
            'date' => $res->reserve_date,
            'timeslot' => $res->timeslot,
            'partySize' => $res->party_size,
            'status' => $res->status,
        ]);
    }
}
