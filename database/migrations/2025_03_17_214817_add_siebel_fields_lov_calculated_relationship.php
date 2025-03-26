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

        // Add relationships to siebel fields based on the calculated value's references to other fields and siebel values
        // Create pivot table for siebel field references (self-referencing relationship)
        Schema::create('siebel_field_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_field_id')->constrained('siebel_fields')->onDelete('cascade');
            $table->foreignId('referenced_field_id')->constrained('siebel_fields')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['parent_field_id', 'referenced_field_id']);
        });

        // Create pivot table for siebel field to siebel values relationship
        Schema::create('siebel_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siebel_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('siebel_value_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['siebel_field_id', 'siebel_value_id']);
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('siebel_field_references');
        Schema::dropIfExists('siebel_field_values');

        Schema::enableForeignKeyConstraints();
    }
};
