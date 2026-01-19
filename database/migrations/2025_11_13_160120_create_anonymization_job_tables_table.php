<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_job_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('anonymization_jobs')->cascadeOnDelete();
            $table->foreignId('table_id')->constrained('anonymous_siebel_tables')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['job_id', 'table_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_job_tables');
    }
};
