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
        Schema::disableForeignKeyConstraints();

        Schema::create('select_options', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('label', 400)->nullable();
            $table->string('value', 400)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('form_field_id')->constrained();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('select_options');
    }
};
