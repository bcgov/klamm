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
        Schema::create('element_conditionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->cascadeOnDelete();
            $table->string('condition_type');
            $table->string('target_element_uuid');
            $table->string('operator');
            $table->string('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('element_conditionals');
    }
};
