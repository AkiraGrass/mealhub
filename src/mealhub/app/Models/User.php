<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\UserStatus;

class User extends Authenticatable
{
    use HasFactory;

    protected $table      = 'users';
    protected $primaryKey = 'id';

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'password', 'status', 'created_by', 'updated_by'];
    protected $hidden   = ['password'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'status'     => UserStatus::class,
    ];

    protected static function booted(): void
    {
        static::updating(function (self $user) {
            if ($user->isDirty('email')) {
                throw new \InvalidArgumentException('email_immutable');
            }
        });
    }

}
