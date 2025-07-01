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
            $table->morphs('optionable'); // Creates optionable_type and optionable_id
            $table->string('label');
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            // Index for better performance
            $table->index(['optionable_type', 'optionable_id', 'order']);
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
