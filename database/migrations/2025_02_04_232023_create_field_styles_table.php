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
        Schema::create('styles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('property');
            $table->string('value');
            $table->timestamps();
        });

        Schema::create('style_instances', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // web or PDF
            $table->foreignId('style_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_instance_field_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('field_group_instance_id')->nullable()->constrained()->onDelete('cascade');
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

        // Remove old style columns
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('styles');
        });

        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->dropColumn('custom_styles');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('styles')->nullable();
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('custom_styles')->nullable();
        });
        Schema::dropIfExists('form_field_style_web');
        Schema::dropIfExists('form_field_style_pdf');
        Schema::dropIfExists('field_group_style_web');
        Schema::dropIfExists('field_group_style_pdf');
        Schema::dropIfExists('style_instances');
        Schema::dropIfExists('styles');
    }
};
