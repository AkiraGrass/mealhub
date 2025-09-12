<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Helper\ApiResponse;
use App\Helper\Status;
use Closure;
use Illuminate\Http\Request;

class CheckApiAccess
{
    public function handle(Request $request, Closure $next, ...$requiredScopes)
    {
        $userId = $request->attributes->get('authUserId');
        if (!$userId) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = User::find($userId);
        if (!$user || $user->status !== 'active') {
            return response()->json(['message' => 'User not allowed'], 403);
        }

        // 可選：檢查 scope
        $tokenScopes = $request->attributes->get('auth_scope', []);
        foreach ($requiredScopes as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                return response()->json(['message' => 'Forbidden (scope)'], 403);
            }
        }

        return $next($request);
    }
}
