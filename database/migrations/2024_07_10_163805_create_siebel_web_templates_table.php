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

        Schema::create('siebel_web_templates', function (Blueprint $table) {
            $table->id();
            $table->longText('definition')->nullable();
            $table->string('name', 400)->nullable();
            $table->boolean('changed')->nullable();
            $table->string('type', 400)->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 500)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_web_templates');
    }
};
