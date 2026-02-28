<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Enums\UserStatus;
use App\Helper\ApiResponse;
use App\Helper\Status;
use Closure;
use Illuminate\Http\Request;

class CheckApiAccess
{
    public function handle(Request $request, Closure $next, ...$requiredScopes)
    {
        $userId = $request->attributes->get('authUserId')
            ?? $request->attributes->get('auth_user_id');
        if (!$userId) {
            return ApiResponse::error(Status::FAILURE, 'unauthorized');
        }

        $user = User::find($userId);
        if (!$user || $user->status !== UserStatus::ACTIVE) {
            return ApiResponse::error(Status::FAILURE, 'forbidden');
        }

        // 可選：檢查 scope
        $tokenScopes = $request->attributes->get('auth_scope', [])
            ?: $request->attributes->get('authScope', []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                return ApiResponse::error(Status::FAILURE, 'forbidden');
            }
        }

        return $next($request);
    }
}
