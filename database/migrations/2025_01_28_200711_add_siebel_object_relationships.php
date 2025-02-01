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

        Schema::create('bre_field_siebel_business_object', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('siebel_business_object_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('bre_field_siebel_business_component', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('siebel_business_component_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bre_field_siebel_business_component');
        Schema::dropIfExists('bre_field_siebel_business_object');
    }
};
