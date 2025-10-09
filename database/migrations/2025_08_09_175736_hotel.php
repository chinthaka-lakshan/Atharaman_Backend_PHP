<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->id();
            $table->string('hotel_name');
            $table->string('nearest_city');
            $table->text('hotel_address');
            $table->string('business_mail')->nullable();
            $table->string('contact_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->text('short_description');
            $table->text('long_description')->nullable();
            $table->json('locations')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_owner_id')->constrained('hotel_owners')->onDelete('cascade');
            $table->timestamps();

            // Column indexes for better performance
            $table->index('nearest_city');
            // Add a generated columns for json arrays
            $table->string('primary_location')->virtualAs('JSON_UNQUOTE(JSON_EXTRACT(`locations`, "$[0]"))');
            $table->index('primary_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hotels');
    }
};