<?php

namespace App\Http\Controllers;

use App\Helper\ApiResponse;
use App\Helper\Status;
use App\Services\UserService;
use App\Services\AuthService;
use App\Http\Resources\UserResource;
use App\Http\Resources\AuthTokensResource;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRefreshRequest;
use App\Http\Requests\UserLogoutRequest;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $userService;
    protected $authService;

    public function __construct(UserService $userService, AuthService $authService)
    {
        $this->userService = $userService;
        $this->authService = $authService;
    }

    public function register(UserRegisterRequest $request)
    {
        $data = $request->validated();
        try {
            $user = $this->userService->register($data);
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'email_taken') {
                return ApiResponse::validationError([
                    'email' => ['The email has already been taken.'],
                ]);
            }
            return ApiResponse::error(Status::FAILURE, 'validationError');
        }

        return ApiResponse::success((new UserResource($user))->toArray($request));
    }

    public function login(UserLoginRequest $request)
    {
        $data = $request->validated();
        try {
            $tokens = $this->authService->login($data, $request->ip());
            return ApiResponse::success((new AuthTokensResource($tokens))->toArray($request));
        } catch (\InvalidArgumentException $e) {
            if ($e->getMessage() === 'invalid_credentials') {
                return ApiResponse::error(Status::FAILURE, 'invalidCredentials');
            }
            return ApiResponse::error(Status::FAILURE, 'failure');
        }
    }

    public function refresh(UserRefreshRequest $request)
    {
        $data = $request->validated();
        try {
            $tokens = $this->authService->refresh($data['refreshToken'], $request->ip());
            return ApiResponse::success((new AuthTokensResource($tokens))->toArray($request));
        } catch (\InvalidArgumentException $e) {
            $key = $e->getMessage();
            if ($key === 'invalid_refresh_token') {
                return ApiResponse::error(Status::FAILURE, 'invalidRefreshToken');
            }
            if ($key === 'refresh_token_replayed') {
                return ApiResponse::error(Status::FAILURE, 'refreshTokenReplayed');
            }
            return ApiResponse::error(Status::FAILURE, 'failure');
        }
    }

    public function logout(UserLogoutRequest $request)
    {
        $data = $request->validated();
        $auth = $request->header('Authorization');
        $this->authService->logout($data['refreshToken'], $auth);
        return ApiResponse::success();
    }

    public function logoutAll(Request $request)
    {
        $userId = $request->attributes->get('auth_user_id')
            ?? $request->attributes->get('authUserId'); // 由 middleware 注入

        if (!$userId) return ApiResponse::error(Status::FAILURE, 'unauthorized');

        $this->authService->logoutAll((int) $userId);
        return ApiResponse::success();
    }
}
