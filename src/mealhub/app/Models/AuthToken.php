<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthToken extends Model
{
    protected $table      = 'auth_token';
    protected $primaryKey = 'id';
    public $incrementing  = true;
    protected $keyType    = 'int';

    protected $fillable = [
        'user_id', 'token_hash', 'token_family_id', 'device_type', 'device_name', 'device_id',
        'scopes', 'ip_addr', 'last_used_at', 'issued_at', 'expires_at', 'revoked_at', 'replaced_by_id',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'last_used_at' => 'datetime',
        'issued_at'    => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];
}
