<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_column_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['category', 'name']);
            $table->index('name');
            $table->index('category');
        });

        Schema::create('anonymization_column_tag_column', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained('anonymization_column_tags')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('anonymous_siebel_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tag_id', 'column_id']);
            $table->index('column_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_column_tag_column');
        Schema::dropIfExists('anonymization_column_tags');
    }
};
