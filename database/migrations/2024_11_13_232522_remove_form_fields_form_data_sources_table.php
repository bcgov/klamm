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
        Schema::dropIfExists('form_fields_form_data_sources');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('form_fields_form_data_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_data_source_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
};
