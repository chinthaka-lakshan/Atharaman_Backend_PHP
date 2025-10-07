<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('hotel_owners', function (Blueprint $table) {
            $table->id();
            $table->string('hotel_owner_name');
            $table->string('hotel_owner_nic', 24)->unique();
            $table->date('hotel_owner_dob');
            $table->text('hotel_owner_address');
            $table->string('business_mail');
            $table->string('contact_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('hotel_owners');
    }
};