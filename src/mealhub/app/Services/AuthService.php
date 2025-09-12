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
     * Authenticate and issue access/refresh tokens.
     * @throws \InvalidArgumentException invalid_credentials
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
     * Rotate refresh token and return new tokens.
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
     * Revoke current access token (blocklist) and the provided refresh token.
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
                // ignore; best-effort blocklist
            }
        }
    }

    /** Revoke all refresh tokens for a user. */
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
