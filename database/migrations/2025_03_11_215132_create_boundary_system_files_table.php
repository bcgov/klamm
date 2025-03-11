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
        Schema::create('boundary_system_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boundary_system_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('file_name')->nullable();
            $table->text('file_description')->nullable();
            $table->foreignId('boundary_system_file_separator_id')->nullable()->constrained('boundary_system_file_separators')->onDelete('cascade');
            $table->foreignId('boundary_system_file_row_separator_id')->nullable()->constrained('boundary_system_file_separators')->onDelete('cascade');
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_system_files');
    }
};
