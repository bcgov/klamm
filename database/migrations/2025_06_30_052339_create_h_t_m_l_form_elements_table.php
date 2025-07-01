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
        Schema::create('h_t_m_l_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('html_content');
            $table->string('repeater_item_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('h_t_m_l_form_elements');
    }
};
