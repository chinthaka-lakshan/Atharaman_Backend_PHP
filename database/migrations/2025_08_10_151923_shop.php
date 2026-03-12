<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('shop_name');
            $table->string('nearest_city');
            $table->text('shop_address')->nullable();
            $table->string('contact_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->text('short_description');
            $table->text('long_description')->nullable();
            $table->longText('locations')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('shop_owner_id')->constrained('shop_owners')->onDelete('cascade');
            $table->timestamps();

            // Column indexes for better performance
            $table->index('nearest_city');
            // primary_location stored as plain column for MySQL compatibility
            $table->string('primary_location')->nullable();
            $table->index('primary_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shops');
    }
};