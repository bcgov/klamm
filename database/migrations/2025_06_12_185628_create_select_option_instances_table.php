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
        Schema::create('select_option_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('label')->nullable();
            $table->string('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('select_option_instances');
    }
};
