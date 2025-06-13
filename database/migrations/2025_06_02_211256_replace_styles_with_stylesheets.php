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
        // Drop the old styles table if it exists
        Schema::dropIfExists('field_group_style_pdf');
        Schema::dropIfExists('field_group_style_web');
        Schema::dropIfExists('form_field_style_pdf');
        Schema::dropIfExists('form_field_style_web');
        Schema::dropIfExists('style_instances');
        Schema::dropIfExists('styles');

        // Create the new style_sheets table
        Schema::create('style_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // web | pdf
            $table->string('filename');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Pivot table for many-to-many with form_versions
        Schema::create('form_version_style_sheet', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_sheet_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->integer('order');
            $table->timestamps();
            $table->index(['form_version_id', 'style_sheet_id']);
            $table->unique(['form_version_id', 'style_sheet_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback: drop the new table and optionally recreate the old one
        Schema::dropIfExists('form_version_style_sheet');
        Schema::dropIfExists('style_sheets');

        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('property');
            $table->string('value');
            $table->timestamps();
        });

        Schema::create('style_instances', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_instance_field_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('field_group_instance_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('container_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Pivot tables
        Schema::create('form_field_style_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_field_style_pdf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('field_group_style_web', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('field_group_style_pdf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
};
