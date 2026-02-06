<?php

namespace App\Repositories;

use App\Models\AuthToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AuthTokenRepository
{
    public function create(array $attributes): AuthToken
    {
        return AuthToken::create($attributes);
    }

    public function findByHash(string $hash): ?AuthToken
    {
        return AuthToken::where('token_hash', $hash)->first();
    }

    public function revokeFamily(string $familyId): void
    {
        AuthToken::where('token_family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function saveReplacement(AuthToken $current, AuthToken $replacement): void
    {
        DB::transaction(function () use ($current, $replacement) {
            $replacement->save();
            $current->update(['replaced_by_id' => $replacement->id, 'last_used_at' => now()]);
        });
    }

    public function revokeByHash(string $hash): void
    {
        AuthToken::where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeByUser(int $userId): void
    {
        AuthToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function invalidateUserTokens(int $userId): void
    {
        DB::table('users')
            ->where('id', $userId)
            ->update(['tokens_invalidated_at' => now(), 'updated_at' => now()]);
    }

    public function upsertBlocklistedAccessToken(string $jti, int $userId, int $exp): void
    {
        DB::table('auth_token_blocklist')->updateOrInsert(
            ['jti' => $jti],
            [
                'user_id'    => $userId,
                'revoked_at' => now(),
                'expires_at' => Carbon::createFromTimestamp($exp),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
