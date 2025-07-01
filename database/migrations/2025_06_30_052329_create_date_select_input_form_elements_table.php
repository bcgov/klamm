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
        Schema::create('date_select_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('placeholder_text')->nullable();
            $table->string('label')->nullable();
            $table->boolean('visible_label')->default(true);
            $table->string('repeater_item_label')->nullable();
            $table->date('min_date')->nullable();
            $table->date('max_date')->nullable();
            $table->date('default_date')->nullable();
            $table->string('date_format')->default('Y-m-d');
            $table->boolean('include_time')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('date_select_input_form_elements');
    }
};
