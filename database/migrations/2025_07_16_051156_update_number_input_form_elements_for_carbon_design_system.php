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
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('placeholder_text', 'placeholder');
            $table->renameColumn('label', 'labelText');
            $table->renameColumn('visible_label', 'hideLabel');
            $table->renameColumn('default_value', 'defaultValue');

            // Add new Carbon Design System fields
            $table->text('helperText')->nullable();
            $table->enum('formatStyle', ['decimal', 'currency', 'integer'])->default('decimal');
        });

        // Change numeric columns to support decimals
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->decimal('min', 10, 2)->nullable()->change();
            $table->decimal('max', 10, 2)->nullable()->change();
            $table->decimal('step', 10, 2)->nullable()->change();
            $table->decimal('defaultValue', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('placeholder', 'placeholder_text');
            $table->renameColumn('labelText', 'label');
            $table->renameColumn('hideLabel', 'visible_label');
            $table->renameColumn('defaultValue', 'default_value');

            // Remove the new fields
            $table->dropColumn(['helperText', 'formatStyle']);
        });

        // Change numeric columns back to integers
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->integer('min')->nullable()->change();
            $table->integer('max')->nullable()->change();
            $table->integer('step')->nullable()->change();
            $table->integer('default_value')->nullable()->change();
        });
    }
};
