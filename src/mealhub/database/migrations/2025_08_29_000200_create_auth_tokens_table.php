<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_token', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主鍵，自增流水號');
            $table->unsignedBigInteger('user_id')->comment('所屬使用者 ID');
            $table->string('token_hash')->comment('Refresh Token 的 SHA-256 雜湊值');
            $table->uuid('token_family_id')->comment('Token 家族 ID，用於 Rotation');
            $table->string('device_type')->comment('登入裝置類型（web/ios/android）');
            $table->string('device_name')->nullable()->comment('裝置名稱/UA');
            $table->string('device_id')->nullable()->comment('裝置唯一識別碼');
            $table->json('scopes')->nullable()->comment('權限範圍');
            $table->string('ip_addr', 45)->nullable()->comment('登入 IP 位址');
            $table->timestampTz('last_used_at')->nullable()->comment('最後使用時間');
            $table->timestampTz('issued_at')->useCurrent()->comment('發行時間');
            $table->timestampTz('expires_at')->comment('到期時間');
            $table->timestampTz('revoked_at')->nullable()->comment('撤銷時間');
            // 注意：PK 是 bigIncrements，因此 replaced_by_id 也使用 bigint 對齊
            $table->bigInteger('replaced_by_id')->nullable()->comment('被替換的新 Token ID');
            $table->timestampTz('created_at', 0)->nullable()->comment('建立時間');
            $table->timestampTz('updated_at', 0)->nullable()->comment('更新時間');

            $table->index('user_id', 'idx_auth_token_user');
            $table->index('token_family_id', 'idx_auth_token_family');
            $table->unique('token_hash', 'ux_auth_token_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_token');
    }
};
