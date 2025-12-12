<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $email = 'test@example.com';

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'first_name' => 'Test',
                'last_name'  => 'User',
                'phone'      => null,
                'password'   => Hash::make('password'),
                'status'     => UserStatus::ACTIVE,
            ]
        );
    }
}
