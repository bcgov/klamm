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

        Schema::create('siebel_applications', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('menu', 50)->nullable();
            $table->boolean('scripted');
            $table->string('acknowledgment_web_page', 100)->nullable();
            $table->string('container_web_page', 250)->nullable();
            $table->string('error_web_page', 50)->nullable();
            $table->string('login_web_page', 100)->nullable();
            $table->string('logoff_acknowledgment_web_page', 250)->nullable();
            $table->string('acknowledgment_web_view', 250)->nullable();
            $table->string('default_find', 30)->nullable();
            $table->boolean('inactive');
            $table->string('comments', 400)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('task_screen_id')->nullable()->constrained('siebel_screens');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_applications');
    }
};
