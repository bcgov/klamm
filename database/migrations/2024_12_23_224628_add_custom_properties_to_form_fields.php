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
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->renameColumn('custom_id', 'instance_id');
            $table->renameColumn('data_binding', 'custom_data_binding');
            $table->renameColumn('data_binding_path', 'custom_data_binding_path');
            $table->renameColumn('styles', 'custom_styles');
            $table->renameColumn('mask', 'custom_mask');
            $table->renameColumn('help_text', 'custom_help_text');
            $table->renameColumn('label', 'custom_label');

            $table->string('customize_label')->nullable();
            $table->string('custom_instance_id')->nullable();
        });

        Schema::table('form_instance_field_values', function (Blueprint $table) {
            $table->renameColumn('value', 'custom_value')->nullable();
        });

        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->renameColumn('custom_id', 'instance_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->renameColumn('instance_id', 'custom_id');
            $table->renameColumn('custom_data_binding', 'data_binding');
            $table->renameColumn('custom_data_binding_path', 'data_binding_path');
            $table->renameColumn('custom_styles', 'styles');
            $table->renameColumn('custom_mask', 'mask');
            $table->renameColumn('custom_help_text', 'help_text');
            $table->renameColumn('custom_label', 'label');

            $table->dropColumn('customize_label')->nullable();
            $table->dropColumn('custom_instance_id')->nullable();
        });

        Schema::table('form_instance_field_values', function (Blueprint $table) {
            $table->renameColumn('custom_value', 'value');
        });

        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->renameColumn('instance_id', 'custom_id');
        });
    }
};
