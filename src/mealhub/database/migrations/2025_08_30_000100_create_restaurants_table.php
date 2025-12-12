<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->text('note')->nullable();
            $table->json('timeslots')->nullable()->comment('可預約時段清單，例如 ["18:00-19:30","19:30-21:00"]');
            $table->json('table_buckets')->nullable()->comment('如 {"2":10,"4":5} 各人數對應桌數');
            $table->string('status')->default('ACTIVE');
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
