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
        Schema::create('anonymization_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('job_type');
            $table->string('status')->default('draft');
            $table->string('output_format');
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('sql_script')->nullable();
            $table->timestamps();

            $table->index('job_type');
            $table->index('status');
            $table->index('output_format');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymization_jobs');
    }
};
