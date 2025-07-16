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
        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('label', 'labelText');
            $table->renameColumn('visible_label', 'hideLabel');
            $table->renameColumn('default_value', 'defaultSelected');

            // Add new Carbon Design System fields
            $table->enum('labelPosition', ['left', 'right'])->default('right');
            $table->text('helperText')->nullable();
            $table->enum('orientation', ['horizontal', 'vertical'])->default('vertical');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('labelText', 'label');
            $table->renameColumn('hideLabel', 'visible_label');
            $table->renameColumn('defaultSelected', 'default_value');

            // Remove the new fields
            $table->dropColumn(['labelPosition', 'helperText', 'orientation']);
        });
    }
};
