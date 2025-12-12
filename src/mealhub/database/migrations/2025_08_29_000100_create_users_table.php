<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主鍵');
            $table->string('first_name')->comment('會員名字');
            $table->string('last_name')->comment('會員姓氏');
            $table->string('email')->nullable()->comment('會員 Email');
            $table->string('phone')->nullable()->comment('會員電話');
            $table->string('password')->comment('會員密碼(Hash)');
            $table->string('status')->default('ACTIVE')->comment('會員狀態');
            $table->timestampTz('tokens_invalidated_at')->nullable()->comment('登出所有裝置的切點');

            // 通用欄位
            $table->timestampTz('created_at', 0)->nullable()->comment('建立時間');
            $table->timestampTz('updated_at', 0)->nullable()->comment('更新時間');
            $table->string('created_by', 64)->nullable()->comment('建立者(SYSTEM / USER:<uuid>)');
            $table->string('updated_by', 64)->nullable()->comment('更新者(SYSTEM / USER:<uuid>)');

            // 索引（email 唯一性改由 lower(email) 的表達式索引實現）
            $table->index('phone');
            $table->index('status');
            $table->index('created_by');
            $table->index('updated_by');
        });
        // PostgreSQL: 建立大小寫不敏感唯一索引（忽略 NULL）
        DB::statement('CREATE UNIQUE INDEX ux_users_email_lower ON users ((lower(email))) WHERE email IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
