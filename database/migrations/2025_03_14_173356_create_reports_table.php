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
        Schema::create('report_label_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('report_business_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('report_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_area_id')->nullable()->constrained('report_business_areas')->nullOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('name');
            $table->string('existing_label');
            $table->foreignId('label_source_id')->nullable()->constrained('report_label_sources')->nullOnDelete();
            $table->string('data_field')->nullable();
            $table->string('icm_data_field_path')->nullable();
            $table->enum('data_matching_rate', ['easy', 'medium', 'complex'])->nullable();
            $table->text('note')->nullable();
            $table->foreignId('last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('report_business_areas');
        Schema::dropIfExists('report_label_sources');
        Schema::dropIfExists('report_entries');
    }
};
