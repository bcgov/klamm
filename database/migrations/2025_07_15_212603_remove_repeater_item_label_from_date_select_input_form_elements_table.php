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
            $table->dropColumn('repeater_item_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('date_select_input_form_elements', function (Blueprint $table) {
            $table->string('repeater_item_label')->nullable();
        });
    }
};
