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
        Schema::create('location_hotel_reviews', function (Blueprint $table) {
            $table->id();
            $table->integer('rating')->min(1)->max(5);
            $table->string('comment')->nullable();
            $table->string('type'); 
            $table->jason('reviewImages')->nullable();
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
        Schema::dropIfExists('location_hotel_reviews');
    }
};
