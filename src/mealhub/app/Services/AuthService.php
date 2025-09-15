<?php

namespace App\Services;

use App\Models\AuthToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class AuthService
{
    public function __construct(private JwtService $jwt)
    {
    }

    /**
     * 使用者登入：驗證帳密，簽發 Access/Refresh Token。
     * - Access Token：短效 JWT（由 JwtService 簽發）。
     * - Refresh Token：使用 `random_bytes` 產生高熵亂數，僅回傳給客戶端；
     *   伺服器側僅保存 SHA-256 雜湊（避免資料外洩時可直接濫用）。
     * @throws \InvalidArgumentException invalid_credentials 帳密錯誤
     */
    public function login(array $data, string $ip): array
    {
        $user = User::query()
            ->when($data['email'] ?? null, fn($q) => $q->whereRaw('LOWER(email) = ?', [strtolower($data['email'])]))
            ->when($data['phone'] ?? null, fn($q) => $q->where('phone', $data['phone']))
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw new \InvalidArgumentException('invalid_credentials');
        }

        $accessToken = $this->jwt->issueAccessToken($user->id, ['scope' => ['user']]);

        // 產生 Refresh Token：
        // - 使用 CSPRNG `random_bytes(32)` 取得 32 bytes 真隨機，再轉 64 字元 hex 字串。
        // - 只儲存雜湊（token 本體僅回傳給客戶端一次），降低洩漏風險。
        $refreshToken        = bin2hex(random_bytes(32));
        $refreshTokenHash    = hash('sha256', $refreshToken);
        $tokenFamilyId       = $this->jwt->uuid();
        $refreshTokenTtlDays = (int) env('JWT_REFRESH_TTL_DAYS', 14);

        AuthToken::create([
            'user_id'         => $user->id,
            'token_hash'      => $refreshTokenHash,
            'token_family_id' => $tokenFamilyId,
            'device_type'     => $data['deviceType'] ?? 'WEB',
            'device_name'     => $data['deviceName'] ?? null,
            'device_id'       => $data['deviceID'] ?? null,
            'ip_addr'         => $ip,
            'issued_at'       => now(),
            'expires_at'      => now()->addDays($refreshTokenTtlDays),
        ]);

        return [
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'tokenType'    => 'Bearer',
            'expiresIn'    => (int) env('JWT_ACCESS_TTL_MIN', 15) * 60,
        ];
    }

    /**
     * 旋轉 Refresh Token（Rotate）：
     * - 驗證舊 refresh token（以雜湊比對、檢查是否撤銷/逾期）。
     * - 若被重放（replayed），撤銷同家族（token_family）所有 token。
     * - 產生新 refresh token（random_bytes）並保存雜湊，回傳新 Access/Refresh。
     * @throws \InvalidArgumentException invalid_refresh_token|refresh_token_replayed
     */
    public function refresh(string $refreshToken, string $ip): array
    {
        $refreshTokenHash  = hash('sha256', $refreshToken);
        $refreshTokenRecord = AuthToken::where('token_hash', $refreshTokenHash)->first();

        if (!$refreshTokenRecord || $refreshTokenRecord->revoked_at || $refreshTokenRecord->expires_at->isPast()) {
            throw new \InvalidArgumentException('invalid_refresh_token');
        }

        if ($refreshTokenRecord->replaced_by_id) {
            AuthToken::where('token_family_id', $refreshTokenRecord->token_family_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            throw new \InvalidArgumentException('refresh_token_replayed');
        }

        // 以相同策略產生新 Refresh Token（高熵亂數 + 僅存雜湊）
        $newRefreshToken     = bin2hex(random_bytes(32));
        $newRefreshTokenHash = hash('sha256', $newRefreshToken);
        $refreshTokenTtlDays = (int) env('JWT_REFRESH_TTL_DAYS', 14);

        $newRefreshRecord = new AuthToken([
            // 不手動指定 id，讓 DB 自增
            'user_id'         => $refreshTokenRecord->user_id,
            'token_hash'      => $newRefreshTokenHash,
            'token_family_id' => $refreshTokenRecord->token_family_id,
            'device_type'     => $refreshTokenRecord->device_type,
            'device_name'     => $refreshTokenRecord->device_name,
            'device_id'       => $refreshTokenRecord->device_id,
            'ip_addr'         => $ip,
            'issued_at'       => now(),
            'expires_at'      => now()->addDays($refreshTokenTtlDays),
        ]);

        // 以交易確保「新增新 token」與「標記舊 token 已被替換」的原子性
        DB::transaction(function () use ($refreshTokenRecord, $newRefreshRecord) {
            $newRefreshRecord->save();
            $refreshTokenRecord->update(['replaced_by_id' => $newRefreshRecord->id, 'last_used_at' => now()]);
        });

        $accessToken = $this->jwt->issueAccessToken($refreshTokenRecord->user_id, ['scope' => ['user']]);

        return [
            'accessToken'  => $accessToken,
            'refreshToken' => $newRefreshToken,
            'tokenType'    => 'Bearer',
            'expiresIn'    => (int) env('JWT_ACCESS_TTL_MIN', 15) * 60,
        ];
    }

    /**
     * 登出：撤銷本次的 Refresh Token，並將 Access Token 的 jti 加入黑名單（blocklist）。
     * - Access Token 採無狀態驗證，透過 jti 對照黑名單達到即時撤銷效果。
     */
    public function logout(string $refreshToken, ?string $authorizationHeader): void
    {
        $refreshTokenHash = hash('sha256', $refreshToken);
        AuthToken::where('token_hash', $refreshTokenHash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($authorizationHeader && str_starts_with($authorizationHeader, 'Bearer ')) {
            $accessToken = substr($authorizationHeader, 7);
            try {
                $payload = $this->jwt->decode($accessToken);
                $jti     = $payload['jti'] ?? null;
                $userId  = $payload['sub'] ?? null;
                $exp     = $payload['exp'] ?? null;
                if ($jti && $userId && $exp) {
                    DB::table('auth_token_blocklist')->updateOrInsert(
                        ['jti' => $jti],
                        [
                            'user_id'   => (int) $userId,
                            'revoked_at'=> now(),
                            'expires_at'=> Carbon::createFromTimestamp($exp),
                            'updated_at'=> now(),
                            'created_at'=> now(),
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // 最佳努力寫入黑名單，失敗不阻塞使用者登出流程
            }
        }
    }

    /**
     * 全部登出：撤銷使用者所有 Refresh Token，並設置使用者層級切點，
     * 讓所有已發出的 Access Token 立即失效（比較 iat 與 tokens_invalidated_at）。
     */
    public function logoutAll(int $userId): void
    {
        // 撤銷所有 refresh tokens
        AuthToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        // 設置使用者層級的切點，讓所有已發出的 access tokens 立即失效
        DB::table('users')
            ->where('id', $userId)
            ->update(['tokens_invalidated_at' => now(), 'updated_at' => now()]);
    }
}
