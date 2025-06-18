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
        Schema::create('number_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('placeholder_text')->nullable();
            $table->string('label')->nullable();
            $table->boolean('visible_label')->default(true);
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->integer('step')->nullable();
            $table->integer('default_value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('number_input_form_elements');
    }
};
