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
        Schema::dropIfExists('form_third_party');
        Schema::dropIfExists('third_parties');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('third_parties', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('form_third_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('third_party_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }
};
