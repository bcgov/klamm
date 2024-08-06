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
        Schema::disableForeignKeyConstraints();

        Schema::create('siebel_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('dll', 100)->nullable();
            $table->string('object_type', 30)->nullable();
            $table->boolean('thin_client');
            $table->boolean('java_thin_client');
            $table->boolean('handheld_client');
            $table->string('unix_support', 10)->nullable();
            $table->string('high_interactivity_enabled', 10)->nullable();
            $table->boolean('inactive');
            $table->string('comments', 500)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('super_class_id')->nullable()->constrained('siebel_classes');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_classes');
    }
};
