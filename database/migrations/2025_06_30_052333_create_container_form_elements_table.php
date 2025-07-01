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
        Schema::create('container_form_elements', function (Blueprint $table) {
            $table->id();
            $table->enum('container_type', ['page', 'fieldset', 'section'])->default('section');
            $table->boolean('collapsible')->default(false);
            $table->boolean('collapsed_by_default')->default(false);
            $table->boolean('is_repeatable')->default(false);
            $table->string('legend')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_form_elements');
    }
};
