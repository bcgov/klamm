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
        // Drop foreign keys on old table
        Schema::table('select_option_instances', function (Blueprint $table) {
            $table->dropForeign(['select_option_id']);
            $table->dropForeign(['form_field_id']);
            $table->dropForeign(['form_instance_field_id']);
        });

        // Rename both tables
        Schema::rename('select_options', 'selectable_values');
        Schema::rename('select_option_instances', 'selectable_value_instances');

        // Rename foreign key columns to match new table name (optional, but consistent)
        Schema::table('selectable_value_instances', function (Blueprint $table) {
            $table->renameColumn('select_option_id', 'selectable_value_id');
        });

        // Re-add foreign keys on new table
        Schema::table('selectable_value_instances', function (Blueprint $table) {
            $table->foreign('selectable_value_id')->references('id')->on('selectable_values')->onDelete('cascade');
            $table->foreign('form_field_id')->references('id')->on('form_fields')->onDelete('cascade');
            $table->foreign('form_instance_field_id')->references('id')->on('form_instance_fields')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys on renamed table
        Schema::table('selectable_value_instances', function (Blueprint $table) {
            $table->dropForeign(['selectable_value_id']);
            $table->dropForeign(['form_field_id']);
            $table->dropForeign(['form_instance_field_id']);
        });

        // Rename column back
        Schema::table('selectable_value_instances', function (Blueprint $table) {
            $table->renameColumn('selectable_value_id', 'select_option_id');
        });

        // Rename tables back
        Schema::rename('selectable_values', 'select_options');
        Schema::rename('selectable_value_instances', 'select_option_instances');

        // Re-add foreign keys
        Schema::table('select_option_instances', function (Blueprint $table) {
            $table->foreign('select_option_id')->references('id')->on('select_options')->onDelete('cascade');
            $table->foreign('form_field_id')->references('id')->on('form_fields')->onDelete('cascade');
            $table->foreign('form_instance_field_id')->references('id')->on('form_instance_fields')->onDelete('cascade');
        });
    }
};
