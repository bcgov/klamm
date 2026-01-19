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
        // Schema::disableForeignKeyConstraints();

        Schema::create('anonymous_siebel_column_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_field_id')->nullable()->constrained('anonymous_siebel_columns')->onDelete('cascade');
            $table->foreignId('child_field_id')->constrained('anonymous_siebel_columns')->onDelete('cascade');
            $table->timestamps();
        });

        // Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_column_dependencies');
    }
};
