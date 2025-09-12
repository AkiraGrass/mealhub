<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use App\Helper\ApiResponse;
use App\Helper\Status;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuthenticateJwt
{
    public function __construct(private JwtService $jwt) {}

    public function handle(Request $request, Closure $next)
    {
        $auth = $request->header('Authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            return ApiResponse::error(Status::FAILURE, 'missingBearerToken');
        }
        $token = substr($auth, 7);

        try {
            $payload = $this->jwt->decode($token);
        } catch (Exception $e) {
            return ApiResponse::error(Status::FAILURE, 'invalidToken');
        }

        // ---- Stateless JWT + blocklist check ----
        $userId = $payload['sub'] ?? null;
        $jti    = $payload['jti'] ?? null;
        if (!$userId || !$jti) {
            return ApiResponse::error(Status::FAILURE, 'invalidToken');
        }

        // 即時登出：若 jti 在 blocklist，立即拒絕
        $revoked = DB::table('auth_token_blocklist')
            ->where('jti', $jti)
            ->whereNotNull('revoked_at')
            ->exists();
        if ($revoked) {
            return ApiResponse::error(Status::FAILURE, 'tokenRevoked');
        }

        // 全域登出：若使用者 tokens_invalidated_at > token iat，也視為無效
        $cutoffRow = DB::table('users')->select('tokens_invalidated_at')->where('id', $userId)->first();
        if ($cutoffRow && !empty($cutoffRow->tokens_invalidated_at)) {
            $cutoff = Carbon::parse($cutoffRow->tokens_invalidated_at)->getTimestamp();
            $iat    = (int) ($payload['iat'] ?? 0);
            if ($iat < $cutoff) {
                return ApiResponse::error(Status::FAILURE, 'tokenRevoked');
            }
        }

        $request->attributes->set('authUserId',   $userId);
        $request->attributes->set('auth_user_id', $userId); // 為相容既有存取
        $request->attributes->set('authScope',    $payload['scope'] ?? []);

        return $next($request);
    }
}
