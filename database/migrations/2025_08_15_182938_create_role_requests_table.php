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
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->json('extra_data')->nullable(); // role-specific request details
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('role_requests');
    }
};
