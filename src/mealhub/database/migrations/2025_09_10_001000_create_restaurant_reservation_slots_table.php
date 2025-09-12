<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_reservation_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->date('reserve_date');
            $table->string('timeslot'); // e.g. 18:00-19:30
            $table->unsignedInteger('party_size');
            $table->unsignedInteger('reserved')->default(0);
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();

            $table->unique(['restaurant_id','reserve_date','timeslot','party_size'], 'ux_resv_slots_unique');
            $table->index(['restaurant_id','reserve_date'], 'idx_resv_slots_rest_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_reservation_slots');
    }
};

