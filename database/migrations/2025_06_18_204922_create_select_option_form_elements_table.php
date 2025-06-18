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
        Schema::create('select_option_form_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('select_input_form_elements_id')->constrained('select_input_form_elements')->onDelete('cascade');
            $table->string('label');
            $table->string('value')->nullable();
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('select_option_form_elements');
    }
};
