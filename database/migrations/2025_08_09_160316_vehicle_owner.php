<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Scema::create('vehicle_owners',function (Blueprint $table) {
            $table->id();
            $table->string('vehicleOwnerName');
            $table->string('vehicleOwnerNic');
            $table->string('businessMail');
            $table->string('personaltNumber');
            $table->string('whatsappNumber');
            $table->json('locations')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vehicle_owners');
    }
};
