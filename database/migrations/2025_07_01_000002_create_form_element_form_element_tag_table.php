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
        Schema::create('form_element_form_element_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_element_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_element_tag_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['form_element_id', 'form_element_tag_id'], 'form_element_tag_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_element_form_element_tag');
    }
};
