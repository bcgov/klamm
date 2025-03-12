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
        Schema::create('boundary_systems', function (Blueprint $table) {
            $table->id();
            $table->string('interface_name')->nullable();
            $table->text('interface_description')->nullable();
            $table->foreignId('boundary_system_source_system_id')->nullable()->constrained('boundary_system_systems')->onDelete('cascade');
            $table->foreignId('boundary_system_target_system_id')->nullable()->constrained('boundary_system_systems')->onDelete('cascade');
            $table->foreignId('boundary_system_mode_of_transfer_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_file_format_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_frequency_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('date_time')->nullable();
            $table->string('source_point_of_contact')->nullable();
            $table->string('target_point_of_contact')->nullable();
            $table->timestamps();
        });

        // Pivot tables
        Schema::create('boundary_system_boundary_system_process', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boundary_system_id')->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_process_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_system_boundary_system_process');
        Schema::dropIfExists('boundary_systems');
    }
};
