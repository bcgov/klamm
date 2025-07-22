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
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });

        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('helperText');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });

        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->text('helperText')->nullable();
        });
    }
};
