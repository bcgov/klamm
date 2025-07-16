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
        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('label', 'labelText');
            $table->renameColumn('visible_label', 'hideLabel');

            // Add new Carbon Design System fields
            $table->boolean('defaultChecked')->default(false);
            $table->text('helperText')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('labelText', 'label');
            $table->renameColumn('hideLabel', 'visible_label');

            // Remove the new fields
            $table->dropColumn(['defaultChecked', 'helperText']);
        });
    }
};
