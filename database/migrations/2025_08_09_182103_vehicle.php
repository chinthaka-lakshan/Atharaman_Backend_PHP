<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_name');
            $table->string('vehicle_type', 50);
            $table->string('reg_number', 24)->unique();
            $table->year('manufactured_year')->nullable();
            $table->unsignedSmallInteger('no_of_passengers');
            $table->string('fuel_type', 24);
            $table->string('driver_status', 50);
            $table->text('short_description');
            $table->text('long_description')->nullable();
            $table->decimal('price_per_day', 10, 2)->nullable();
            $table->unsignedInteger('mileage_per_day')->nullable();
            $table->json('locations')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('vehicle_owner_id')->constrained('vehicle_owners')->onDelete('cascade');
            $table->timestamps();

            // Column indexes for better performance
            $table->index('vehicle_type');
            $table->index('no_of_passengers');
            $table->index('fuel_type');
            $table->index('driver_status');
            $table->index('price_per_day');
            $table->index('mileage_per_day');
            // Add a generated columns for json arrays
            $table->string('primary_location')->virtualAs('JSON_UNQUOTE(JSON_EXTRACT(`locations`, "$[0]"))');
            $table->index('primary_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicles');
    }
};