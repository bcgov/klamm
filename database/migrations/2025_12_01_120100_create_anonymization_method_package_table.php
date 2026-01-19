<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_method_package', function (Blueprint $table) {
            $table->id();
            $table->foreignId('anonymization_method_id')
                ->constrained('anonymization_methods')
                ->cascadeOnDelete();
            $table->foreignId('anonymization_package_id')
                ->constrained('anonymization_packages')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['anonymization_method_id', 'anonymization_package_id'], 'method_package_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_method_package');
    }
};
