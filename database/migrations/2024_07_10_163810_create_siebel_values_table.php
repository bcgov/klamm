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

        Schema::create('siebel_values', function (Blueprint $table) {
            $table->id();
            $table->boolean('inactive');
            $table->string('type', 50)->nullable();
            $table->longText('display_value')->nullable();
            $table->boolean('changed')->nullable();
            $table->boolean('translate')->nullable();
            $table->boolean('multilingual')->nullable();
            $table->string('language_independent_code', 50)->nullable();
            $table->string('parent_lic', 50)->nullable();
            $table->string('high', 300)->nullable();
            $table->string('low', 300)->nullable();
            $table->integer('order')->nullable();
            $table->boolean('active')->nullable();
            $table->string('language_name', 200)->nullable();
            $table->string('replication_level', 25)->nullable();
            $table->integer('target_low')->nullable();
            $table->integer('target_high')->nullable();
            $table->integer('weighting_factor')->nullable();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_values');
    }
};
