<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_token_blocklist', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('jti')->unique()->comment('JWT ID');
            $table->unsignedBigInteger('user_id')->index('idx_blocklist_user');
            $table->timestampTz('revoked_at')->nullable()->comment('撤銷時間');
            $table->timestampTz('expires_at')->comment('存活到期時間，對齊 JWT exp');
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_token_blocklist');
    }
};
