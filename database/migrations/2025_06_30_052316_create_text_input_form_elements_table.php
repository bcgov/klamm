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
        Schema::create('text_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('placeholder_text')->nullable();
            $table->string('label')->nullable();
            $table->boolean('visible_label')->default(true);
            $table->string('mask')->nullable();
            $table->integer('maxlength')->nullable();
            $table->integer('minlength')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('text_input_form_elements');
    }
};
