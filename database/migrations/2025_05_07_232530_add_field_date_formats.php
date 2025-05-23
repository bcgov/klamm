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
        Schema::create('form_field_date_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->string('date_format');
            $table->timestamps();
        });

        Schema::create('form_instance_field_date_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_instance_field_id')->constrained()->onDelete('cascade');
            $table->string('custom_date_format');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_field_date_formats');
        Schema::dropIfExists('form_instance_field_date_formats');
    }
};
