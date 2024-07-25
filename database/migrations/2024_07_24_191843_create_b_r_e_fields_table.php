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

        Schema::create('b_r_e_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('label', 400)->nullable();
            $table->text('help_text')->nullable();
            $table->foreignId('data_type_id')->constrained();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_r_e_fields');
    }
};
