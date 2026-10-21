<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('checkbox_group_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultSelected', 'default_selected');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultChecked', 'default_checked');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('currency_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('enableVarSub', 'enable_var_sub');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultValue', 'default_value');
            $table->renameColumn('labelText', 'label_text');
        });
        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('minDate', 'min_date');
            $table->renameColumn('maxDate', 'max_date');
            $table->renameColumn('dateFormat', 'date_format');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultValue', 'default_value');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
            $table->renameColumn('maskType', 'mask_type');
        });
        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultSelected', 'default_selected');
            $table->renameColumn('labelPosition', 'label_position');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('defaultSelected', 'default_selected');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('maxCount', 'max_count');
            $table->renameColumn('defaultValue', 'default_value');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
        });
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('labelText', 'label_text');
            $table->renameColumn('hideLabel', 'hide_label');
            $table->renameColumn('maxCount', 'max_count');
            $table->renameColumn('defaultValue', 'default_value');
            $table->renameColumn('enableVarSub', 'enable_var_sub');
            $table->renameColumn('maskType', 'mask_type');
            $table->renameColumn('maskErrorMessage', 'mask_error_message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('checkbox_group_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_selected', 'defaultSelected');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_checked', 'defaultChecked');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('currency_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('enable_var_sub', 'enableVarSub');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_value', 'defaultValue');
            $table->renameColumn('label_text', 'labelText');
        });
        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('min_date', 'minDate');
            $table->renameColumn('max_date', 'maxDate');
            $table->renameColumn('date_format', 'dateFormat');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_value', 'defaultValue');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
            $table->renameColumn('mask_type', 'maskType');
        });
        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_selected', 'defaultSelected');
            $table->renameColumn('label_position', 'labelPosition');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('default_selected', 'defaultSelected');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('enable_var_sub', 'enableVarSub');
        });
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->renameColumn('label_text', 'labelText');
            $table->renameColumn('hide_label', 'hideLabel');
            $table->renameColumn('max_count', 'maxCount');
            $table->renameColumn('default_value', 'defaultValue');
            $table->renameColumn('enable_var_sub', 'enableVarSub');
            $table->renameColumn('mask_type', 'maskType');
            $table->renameColumn('mask_error_message', 'maskErrorMessage');
        });
    }
};
