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
        Schema::create('siebel_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->foreignId('business_component_id')->nullable()->constrained('siebel_business_components');
            $table->foreignId('table_id')->nullable()->constrained('siebel_tables');
            $table->string('table_column', 400)->nullable();
            $table->string('multi_value_link', 400)->nullable();
            $table->string('multi_value_link_field', 400)->nullable();
            $table->string('join', 400)->nullable();
            $table->string('join_column', 400)->nullable();
            $table->string('calculated_value', 400)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_fields');
    }
};
