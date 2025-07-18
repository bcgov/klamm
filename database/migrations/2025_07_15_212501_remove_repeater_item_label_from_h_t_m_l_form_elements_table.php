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
        Schema::table('h_t_m_l_form_elements', function (Blueprint $table) {
            $table->dropColumn(['repeater_item_label', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('h_t_m_l_form_elements', function (Blueprint $table) {
            $table->string('repeater_item_label')->nullable();
            $table->string('name')->nullable();
        });
    }
};
