<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use App\Helper\Status;
use App\Http\Requests\UserUpdateSelfRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function me(Request $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');
        $user = $this->userService->me((int) $userId);
        return ApiResponse::success((new UserResource($user))->toArray($request));
    }

    public function update(UserUpdateSelfRequest $request)
    {
        $userId = $request->attributes->get('auth_user_id') ?? $request->attributes->get('authUserId');
        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $user = $this->userService->updateSelf((int) $userId, $request->validated());
        return ApiResponse::success((new UserResource($user))->toArray($request));
    }
}
