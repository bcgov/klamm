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
        Schema::create('form_element_data_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_element_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_data_source_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->integer('order')->default(0);
            $table->timestamps();

            // Add unique constraint to prevent duplicate data source bindings per element
            $table->unique(['form_element_id', 'form_data_source_id'], 'unique_element_data_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_element_data_bindings');
    }
};
