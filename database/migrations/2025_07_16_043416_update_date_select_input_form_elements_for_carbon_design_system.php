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
        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('placeholder_text', 'placeholder');
            $table->renameColumn('label', 'labelText');
            $table->renameColumn('visible_label', 'hideLabel');
            $table->renameColumn('min_date', 'minDate');
            $table->renameColumn('max_date', 'maxDate');
            $table->renameColumn('date_format', 'dateFormat');

            // Add new Carbon Design System fields
            $table->text('helperText')->nullable();

            // Remove fields not used in Carbon Design System
            $table->dropColumn(['default_date', 'include_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('placeholder', 'placeholder_text');
            $table->renameColumn('labelText', 'label');
            $table->renameColumn('hideLabel', 'visible_label');
            $table->renameColumn('minDate', 'min_date');
            $table->renameColumn('maxDate', 'max_date');
            $table->renameColumn('dateFormat', 'date_format');

            // Remove the new fields
            $table->dropColumn('helperText');

            // Add back the removed fields
            $table->date('default_date')->nullable();
            $table->boolean('include_time')->default(false);
        });
    }
};
