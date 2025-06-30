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
        Schema::create('button_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->enum('button_type', ['submit', 'reset', 'button'])->default('button');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('button_input_form_elements');
    }
};
