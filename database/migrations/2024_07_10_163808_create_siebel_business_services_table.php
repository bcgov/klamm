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

        Schema::create('siebel_business_services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->boolean('cache')->nullable();
            $table->string('display_name', 200)->nullable();
            $table->string('display_name_string_reference', 200)->nullable();
            $table->string('display_name_string_override', 200)->nullable();
            $table->boolean('external_use')->nullable();
            $table->boolean('hidden')->nullable();
            $table->boolean('server_enabled');
            $table->string('state_management_type', 25)->nullable();
            $table->boolean('web_service_enabled')->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 500)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('class_id')->nullable()->constrained('siebel_classes');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_business_services');
    }
};
