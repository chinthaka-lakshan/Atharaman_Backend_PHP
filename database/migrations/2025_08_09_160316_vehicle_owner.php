<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('vehicle_owners',function (Blueprint $table) {
            $table->id();
            $table->string('vehicle_owner_name');
            $table->string('vehicle_owner_nic', 24)->unique();
            $table->date('vehicle_owner_dob');
            $table->text('vehicle_owner_address');
            $table->string('business_mail');
            $table->string('contact_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->longText('locations')->nullable();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->timestamps();

            // primary_location stored as plain column for MySQL compatibility
            $table->string('primary_location')->nullable();
            $table->index('primary_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_owners');
    }
};