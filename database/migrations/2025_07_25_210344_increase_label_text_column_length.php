<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Increase labelText column length for all form element tables
        $tables = [
            'text_input_form_elements',
            'textarea_input_form_elements',
            'number_input_form_elements',
            'date_select_input_form_elements',
            'checkbox_input_form_elements',
            'select_input_form_elements',
            'radio_input_form_elements',
        ];

        foreach ($tables as $tableName) {
            // First, update any NULL values to empty strings
            DB::table($tableName)->whereNull('labelText')->update(['labelText' => '']);

            // Then change the column type to allow longer text and ensure it's nullable
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('labelText', 1000)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert labelText column length back to 255 characters
        $tables = [
            'text_input_form_elements',
            'textarea_input_form_elements',
            'number_input_form_elements',
            'date_select_input_form_elements',
            'checkbox_input_form_elements',
            'select_input_form_elements',
            'radio_input_form_elements',
        ];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('labelText', 255)->nullable()->change();
            });
        }
    }
};
