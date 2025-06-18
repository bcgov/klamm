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
        Schema::disableForeignKeyConstraints();

        // Drop tables in order of dependencies (child tables first)

        // Drop validation and value tables first
        Schema::dropIfExists('form_instance_field_validations');
        Schema::dropIfExists('form_field_validations');
        Schema::dropIfExists('form_instance_field_values');
        Schema::dropIfExists('form_field_values');
        Schema::dropIfExists('form_instance_field_conditionals');
        Schema::dropIfExists('form_instance_field_date_formats');
        Schema::dropIfExists('form_field_date_formats');

        // Drop pivot tables
        Schema::dropIfExists('form_versions_form_data_sources');
        Schema::dropIfExists('field_group_form_field');
        Schema::dropIfExists('form_field_style_web');
        Schema::dropIfExists('form_field_style_pdf');
        Schema::dropIfExists('field_group_style_web');
        Schema::dropIfExists('field_group_style_pdf');
        Schema::dropIfExists('select_option_instances');

        // Drop instance tables
        Schema::dropIfExists('style_instances');
        Schema::dropIfExists('form_instance_fields');
        Schema::dropIfExists('field_group_instances');
        Schema::dropIfExists('containers');

        // Drop core form building tables
        Schema::dropIfExists('select_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('field_groups');
        Schema::dropIfExists('form_data_sources');
        Schema::dropIfExists('styles');
        Schema::dropIfExists('data_types');
        Schema::dropIfExists('value_types');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is destructive and cannot be reversed
        // The original migration files still exist to recreate the tables if needed
        throw new \Exception('This migration is destructive and cannot be reversed. Use the original migration files to recreate the tables if needed.');
    }
};
