<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('guide_name');
            $table->string('guide_nic', 24)->unique();
            $table->date('guide_dob');
            $table->string('guide_gender', 15);
            $table->text('guide_address');
            $table->string('business_mail');
            $table->string('contact_number', 15);
            $table->string('whatsapp_number', 15)->nullable();
            $table->text('short_description');
            $table->text('long_description')->nullable();
            $table->json('languages')->nullable();
            $table->json('locations')->nullable();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->timestamps();

            // Column indexes for better performance
            $table->index('guide_gender');
            // Add a generated columns for json arrays
            $table->string('primary_language')->virtualAs('JSON_UNQUOTE(JSON_EXTRACT(`languages`, "$[0]"))');
            $table->index('primary_language');
            $table->string('primary_location')->virtualAs('JSON_UNQUOTE(JSON_EXTRACT(`locations`, "$[0]"))');
            $table->index('primary_location');
        });
    }

    public function down()
    {
        Schema::dropIfExists('guides');
    }
};