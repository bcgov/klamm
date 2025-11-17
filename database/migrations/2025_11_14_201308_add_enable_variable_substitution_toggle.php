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
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('kind');
        });

        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('default_checked');
        });

        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('level');
        });

        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('dateFormat');
        });

        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('formatStyle');
        });

        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('orientation');
        });

        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('defaultSelected');
        });

        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('defaultValue');
        });

        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->boolean('enableVarSub')->default(false)->after('defaultValue');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });
        
        Schema::table('checkbox_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('radio_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('select_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });

        Schema::table('textarea_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('enableVarSub');
        });
    }
};
