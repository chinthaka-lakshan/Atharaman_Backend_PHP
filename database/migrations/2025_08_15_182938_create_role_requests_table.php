<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::create('role_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->json('extra_data')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicate pending requests
            $table->unique(['user_id', 'role_id', 'status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('role_requests');
    }
};