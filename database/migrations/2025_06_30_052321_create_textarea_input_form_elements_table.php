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
        Schema::create('textarea_input_form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('placeholder_text')->nullable();
            $table->string('label')->nullable();
            $table->boolean('visible_label')->default(true);
            $table->integer('rows')->nullable();
            $table->integer('cols')->nullable();
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
        Schema::dropIfExists('textarea_input_form_elements');
    }
};
