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
        // Pivot table for polymorphic many-to-many relationship between validators and form element types
        Schema::create('form_field_validatorables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_field_validator_id')->constrained('form_field_validators')->onDelete('cascade');
            $table->unsignedBigInteger('validatorable_id');
            $table->string('validatorable_type');
            $table->timestamps();

            // Index for polymorphic relationship
            $table->index(['validatorable_id', 'validatorable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_field_validatorables');
    }
};
