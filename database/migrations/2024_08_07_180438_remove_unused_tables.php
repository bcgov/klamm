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
        Schema::dropIfExists('activity_business_form');
        Schema::dropIfExists('branch_program');
        Schema::dropIfExists('business_form_business_form_group');
        Schema::dropIfExists('business_form_form_group');
        Schema::dropIfExists('business_form_form_repository');
        Schema::dropIfExists('business_form_group_program');
        Schema::dropIfExists('business_form_program');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('business_form_groups');
        Schema::dropIfExists('data_source_fields');
        Schema::dropIfExists('datasources');
        Schema::dropIfExists('data_sources');
        Schema::dropIfExists('divisions');
        Schema::dropIfExists('form_builders');
        Schema::dropIfExists('p_d_f_templates');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('business_forms');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->text('summary');
            $table->text('description');
            $table->foreignId('submitter')->constrained('users');
            $table->text('ado_item')->nullable();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 20);
            $table->string('name', 400);
            $table->foreignId('division_id')->constrained();
            $table->timestamps();
        });

        Schema::create('business_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('code', 400)->nullable();
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->text('internal_description')->nullable();
            $table->text('ado_identifier')->nullable();
            $table->timestamps();
        });

        Schema::create('business_form_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->text('documentation')->nullable();
            $table->timestamps();
        });

        Schema::create('datasources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('data_source_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_source_id')->constrained();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('divisions', function (Blueprint $table) {
            $table->id();
            $table->string('short_name', 20);
            $table->string('name', 400);
            $table->foreignId('ministry_id')->constrained();
            $table->timestamps();
        });

        Schema::create('form_builders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('p_d_f_templates', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->foreignId('business_form_id')->constrained();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400)->nullable();
            $table->string('short_name', 20);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_business_form', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_form_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('branch_program', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('business_form_business_form_group', function (Blueprint $table) {
            $table->foreignId('business_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_form_group_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('business_form_form_group', function (Blueprint $table) {
            $table->foreignId('business_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_group_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('business_form_form_repository', function (Blueprint $table) {
            $table->foreignId('business_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_repository_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('business_form_group_program', function (Blueprint $table) {
            $table->foreignId('business_form_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('business_form_program', function (Blueprint $table) {
            $table->foreignId('business_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }
};
