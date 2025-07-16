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
        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('placeholder_text', 'placeholder');
            $table->renameColumn('label', 'labelText');
            $table->renameColumn('visible_label', 'hideLabel');
            $table->renameColumn('maxlength', 'maxCount');

            // Add new Carbon Design System fields
            $table->string('defaultValue')->nullable();
            $table->text('helperText')->nullable();

            // Remove field not used in Carbon Design System
            $table->dropColumn('minlength');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('placeholder', 'placeholder_text');
            $table->renameColumn('labelText', 'label');
            $table->renameColumn('hideLabel', 'visible_label');
            $table->renameColumn('maxCount', 'maxlength');

            // Remove the new fields
            $table->dropColumn(['defaultValue', 'helperText']);

            // Add back the removed field
            $table->integer('minlength')->nullable();
        });
    }
};
