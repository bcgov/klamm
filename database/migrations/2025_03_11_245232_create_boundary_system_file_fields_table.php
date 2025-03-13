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
        Schema::create('boundary_system_file_fields', function (Blueprint $table) {
            $table->id();
            $table->string('field_name')->nullable();
            $table->text('field_description')->nullable();
            $table->foreignId('boundary_system_file_field_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->unsignedSmallInteger('field_length')->nullable();
            $table->text('validations')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_system_file_fields');
    }
};
