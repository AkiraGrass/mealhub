<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('user_id');
            $table->date('reserve_date');
            $table->string('timeslot'); // e.g. 18:00-19:30
            $table->unsignedInteger('party_size');
            $table->string('status')->default('CONFIRMED'); // CONFIRMED|CANCELLED
            $table->uuid('code')->unique()->comment('查詢代碼');
            $table->string('short_token', 32)->unique()->comment('匿名短連結 token');
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();

            $table->index(['restaurant_id','reserve_date','timeslot']);
            $table->index(['user_id']);
        });

        Schema::create('reservation_guests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reservation_id');
            $table->string('email');
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();

            $table->index('reservation_id');
        });

        // 唯一：同一使用者對同餐廳僅能有一筆有效定位（confirmed）
        DB::statement("CREATE UNIQUE INDEX ux_user_restaurant_active_resv ON reservations (restaurant_id, user_id) WHERE status = 'CONFIRMED'");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_guests');
        Schema::dropIfExists('reservations');
    }
};
