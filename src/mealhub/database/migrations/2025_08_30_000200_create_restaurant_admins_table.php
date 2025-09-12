<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('restaurant_admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('restaurant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role')->default('admin');
            $table->timestampTz('created_at', 0)->nullable();
            $table->timestampTz('updated_at', 0)->nullable();

            $table->unique(['restaurant_id','user_id'], 'ux_restaurant_admin');
            $table->index('restaurant_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_admins');
    }
};

