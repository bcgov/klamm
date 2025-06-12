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
        Schema::dropIfExists('containers');

        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->cascadeOnDelete();
            $table->boolean('has_repeater')->default(false);
            $table->boolean('has_clear_button')->default(false);
            $table->string('repeater_item_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
