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
        Schema::create('boundary_system_file_field_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boundary_system_file_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_file_field_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_file_field_map_sections_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('file_structure')->nullable();
            $table->boolean('mandatory')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_system_file_field_maps');
    }
};
