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
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('form_id');
            $table->string('form_title');
            $table->foreignId('ministry_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->foreignId('fill_type_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean('decommissioned')->nullable()->default(false);
            $table->boolean('distribution_centre_victoria')->nullable()->default(false);
            $table->foreignId('form_frequency_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('form_reach_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        // Pivot tables
        Schema::create('form_business_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('business_area_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_form_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_location_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_repository_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_repository_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_software_source_form', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_software_source_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_third_party', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('third_party_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('form_user_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_type_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('related_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_form_id')->constrained('forms')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forms');
        Schema::dropIfExists('form_business_area');
        Schema::dropIfExists('form_form_location');
        Schema::dropIfExists('form_repository_form');
        Schema::dropIfExists('form_software_source_form');
        Schema::dropIfExists('form_third_party');
        Schema::dropIfExists('form_user_type');
        Schema::dropIfExists('related_forms');
    }
};
