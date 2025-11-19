<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_method_column', function (Blueprint $table) {
            $table->id();
            $table->foreignId('method_id')->constrained('anonymization_methods')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('anonymous_siebel_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['method_id', 'column_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_method_column');
    }
};
