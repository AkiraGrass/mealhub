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
}
