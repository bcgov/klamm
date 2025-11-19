<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_job_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('anonymization_jobs')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('anonymous_siebel_columns')->cascadeOnDelete();
            $table->foreignId('anonymization_method_id')->nullable()->constrained('anonymization_methods')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'column_id']);
            $table->index('anonymization_method_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_job_columns');
    }
};
