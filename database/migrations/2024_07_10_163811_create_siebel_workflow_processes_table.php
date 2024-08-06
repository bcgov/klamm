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

        Schema::create('siebel_workflow_processes', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_persist');
            $table->string('process_name', 400);
            $table->string('simulate_workflow_process', 400);
            $table->string('status', 40)->nullable();
            $table->string('workflow_mode', 40)->nullable();
            $table->boolean('changed');
            $table->string('group', 40)->nullable();
            $table->integer('version')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('error_process_name', 400)->nullable();
            $table->string('state_management_type', 40)->nullable();
            $table->boolean('web_service_enabled')->nullable();
            $table->boolean('pass_by_ref_hierarchy_argument')->nullable();
            $table->string('repository_name', 100)->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 500)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('business_object_id')->nullable()->constrained('siebel_business_objects');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_workflow_processes');
    }
};
