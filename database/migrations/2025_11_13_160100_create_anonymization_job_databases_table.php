<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_job_databases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('anonymization_jobs')->cascadeOnDelete();
            $table->foreignId('database_id')->constrained('anonymous_siebel_databases')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'database_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_job_databases');
    }
};
