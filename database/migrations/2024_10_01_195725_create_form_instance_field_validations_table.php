<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_instance_field_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_instance_field_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('value')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
        Schema::create('form_field_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('value')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_instance_field_validations');
    }
};
