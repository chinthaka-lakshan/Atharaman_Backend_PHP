<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guide_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->integer('order_index')->default(0); // For ordering images
            $table->string('alt_text')->nullable();
            $table->timestamps();
            
            // Index for better performance
            $table->index(['guide_id', 'order_index']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('guide_images');
    }
};