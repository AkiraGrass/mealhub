<?php

namespace App\Services;

use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Support\Facades\Hash;

class UserService
{
    /**
     * Register a new user with pre-checks.
     * - Checks duplicate email (case-sensitive as per DB collation)
     */
    public function register(array $data): User
    {
        if (!empty($data['email'])) {
            $email = trim($data['email']);
            $exists = User::whereRaw('LOWER(email) = ?', [strtolower($email)])->exists();
            if ($exists) {
                throw new \InvalidArgumentException('email_taken');
            }
            $data['email'] = $email;
        }

        return User::create([
            'first_name' => $data['firstName'],
            'last_name'  => $data['lastName'],
            'email'      => $data['email'] ?? null,
            'phone'      => $data['phone'] ?? null,
            'password'   => Hash::make($data['password']),
            'status'     => UserStatus::ACTIVE,
        ]);
    }

    /**
     * 取得目前使用者資料
     */
    public function me(int $userId): User
    {
        return User::findOrFail($userId);
    }

    /**
     * 更新目前使用者資料
     */
    public function updateSelf(int $userId, array $data): User
    {
        $user = User::findOrFail($userId);

        $payload = [];
        if (isset($data['firstName'])) $payload['first_name'] = $data['firstName'];
        if (isset($data['lastName']))  $payload['last_name']  = $data['lastName'];
        if (isset($data['phone']))     $payload['phone']      = $data['phone'];
        if (isset($data['password']))  $payload['password']   = Hash::make($data['password']);

        if (!empty($payload)) {
            $user->update($payload);
        }

        return $user->refresh();
    }
}
