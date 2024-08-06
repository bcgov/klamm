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

        Schema::create('siebel_screens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('bitmap_category', 50)->nullable();
            $table->string('viewbar_text', 400)->nullable();
            $table->string('viewbar_text_string_reference', 400)->nullable();
            $table->string('viewbar_text_string_override', 100)->nullable();
            $table->boolean('unrestricted_viewbar')->nullable();
            $table->string('help_identifier', 100)->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 500)->nullable();
            $table->string('upgrade_behavior', 30)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('default_view_id')->nullable()->constrained('siebel_views');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_screens');
    }
};
