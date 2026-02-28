<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use App\Helper\Status;
use App\Http\Requests\RestaurantCreateRequest;
use App\Http\Requests\RestaurantAddAdminRequest;
use App\Http\Requests\RestaurantRemoveAdminRequest;
use App\Http\Resources\RestaurantResource;
use App\Http\Resources\RestaurantAdminResource;
use App\Services\RestaurantService;
use App\Services\ReservationService;
use App\Http\Requests\RestaurantAvailabilityRequest;
use App\Http\Requests\RestaurantAvailabilityByDateRequest;
use App\Http\Requests\RestaurantUpdateTimeslotsRequest;
use App\Http\Requests\RestaurantUpdateRequest;
use App\Http\Requests\RestaurantReservationsRequest;
use Illuminate\Http\Request;

/**
 * 餐廳 API 控制器
 * - 建立/更新/管理者維護/可用量查詢 等
 */
class RestaurantController extends Controller
{
    public function __construct(private RestaurantService $service, private ReservationService $reservationService) {}

    /** 建立餐廳：呼叫者將成為該餐廳管理員（需要登入） */
    public function create(RestaurantCreateRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $restaurant = $this->service->create($request->validated(), (int)$userId);
        return ApiResponse::success((new RestaurantResource($restaurant))->toArray($request));
    }

    /** 新增管理員（需管理員權限） */
    public function addAdmin($restaurantId, RestaurantAddAdminRequest $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        try {
            $this->service->addAdmin((int)$restaurantId, (int)$authUserId, (int)$request->validated()['userId']);
            return ApiResponse::success();
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }
    }

    /** 移除管理員（不可移除自己；至少保留一人，需管理員權限） */
    public function removeAdmin($restaurantId, RestaurantRemoveAdminRequest $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        try {
            $this->service->removeAdmin((int)$restaurantId, (int)$authUserId, (int)$request->validated()['userId']);
            return ApiResponse::success();
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }
    }

    /** 餐廳列表（支援名稱/狀態篩選與分頁） */
    public function index(Request $request)
    {
        $filters = [
            'name'    => $request->query('name'),
            'status'  => $request->query('status'),
            'page'    => (int) $request->query('page', 1),
            'perPage' => (int) $request->query('perPage', 10),
        ];
        $result = $this->service->list($filters);
        $items = array_map(fn($restaurant) => (new RestaurantResource($restaurant))->toArray($request), $result['items']);
        return ApiResponse::success($items, null, \App\Helper\Status::SUCCESS, $result['meta']);
    }

    /** 管理者列表（需管理員權限） */
    public function admins($restaurantId, Request $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        try {
            $users = $this->service->listAdmins((int)$restaurantId, (int)$authUserId);
            $items = $users->map(fn($u) => (new RestaurantAdminResource($u))->toArray($request))->all();
            return ApiResponse::success($items);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }
    }

    /** 餐廳可訂時段查詢（單一人數桶） */
    public function availability($restaurantId, RestaurantAvailabilityRequest $request)
    {
        $data = $request->validated();
        $rows = $this->reservationService->availability((int)$restaurantId, $data['date'], (int)$data['partySize']);
        return ApiResponse::success($rows);
    }

    /** 餐廳可訂時段（詳細：各人數桶） */
    public function availabilityDetail($restaurantId, RestaurantAvailabilityByDateRequest $request)
    {
        $data = $request->validated();
        $rows = $this->reservationService->availabilityDetail((int)$restaurantId, $data['date']);
        return ApiResponse::success($rows);
    }

    /** 更新餐廳可預約時段（需管理員權限；不得移除仍有效之時段） */
    public function updateTimeslots($restaurantId, RestaurantUpdateTimeslotsRequest $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        try {
            $this->service->updateTimeslots((int)$restaurantId, (int)$authUserId, $request->validated()['timeslots']);
            return ApiResponse::success();
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }
    }

    /** 管理用：查看某日期（可選 timeslot）的訂位彙總與清單 */
    public function reservations($restaurantId, RestaurantReservationsRequest $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        // 僅限餐廳管理員
        try {
            $this->service->listAdmins((int)$restaurantId, (int)$authUserId); // 利用已存在的權限檢查
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, 'forbidden');
        }

        $q = $request->validated();
        $data = $this->reservationService->reservationsOverview(
            (int)$restaurantId,
            $q['date'],
            $q['timeslot'] ?? null
        );
        return ApiResponse::success($data);
    }

    /** 更新餐廳資料（名稱/描述/地址/備註/桌數桶/時段，需管理員權限） */
    public function update($restaurantId, RestaurantUpdateRequest $request)
    {
        $authUserId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$authUserId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        try {
            $restaurant = $this->service->updateDetails((int)$restaurantId, (int)$authUserId, $request->validated());
            return ApiResponse::success((new RestaurantResource($restaurant))->toArray($request));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(Status::FAILURE, $e->getMessage());
        }
    }

    /** 顯示單一餐廳詳細（畫面顯示用） */
    public function show($restaurantId, Request $request)
    {
        $restaurant = $this->service->getById((int) $restaurantId);
        return ApiResponse::success((new RestaurantResource($restaurant))->toArray($request));
    }
}
