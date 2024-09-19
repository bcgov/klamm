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

        Schema::create('bre_field_bre_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_field_id')->nullable()->constrained('bre_fields')->onDelete('cascade');
            $table->foreignId('child_field_id')->constrained('bre_fields')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bre_field_bre_field');
    }
};
