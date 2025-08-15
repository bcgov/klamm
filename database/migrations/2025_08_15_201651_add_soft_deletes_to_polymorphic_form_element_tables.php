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
        // Add soft deletes to all polymorphic form element tables
        $tables = [
            'container_form_elements',
            'text_input_form_elements',
            'textarea_input_form_elements',
            'number_input_form_elements',
            'checkbox_input_form_elements',
            'radio_input_form_elements',
            'select_input_form_elements',
            'select_option_form_elements',
            'date_select_input_form_elements',
            'button_input_form_elements',
            'text_info_form_elements',
            'h_t_m_l_form_elements', // HTMLFormElement uses this table name
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from all polymorphic form element tables
        $tables = [
            'container_form_elements',
            'text_input_form_elements',
            'textarea_input_form_elements',
            'number_input_form_elements',
            'checkbox_input_form_elements',
            'radio_input_form_elements',
            'select_input_form_elements',
            'select_option_form_elements',
            'date_select_input_form_elements',
            'button_input_form_elements',
            'text_info_form_elements',
            'h_t_m_l_form_elements', // HTMLFormElement uses this table name
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
