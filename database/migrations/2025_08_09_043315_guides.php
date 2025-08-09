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
        Schema::create('guides', function (Blueprint $table) {
            $table-> id();
            $table->string('guideName');
            $table->string('guideNic');
            $table->string('businessMail');
            $table->string('personalNumber');
            $table->string('whatsappNumber');
            $table->json('guideImage')->nullable();
            $table->json('languages')->nullable();
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
        Schema::dropIfExists('guides');
    }
};
