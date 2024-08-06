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
        Schema::create('siebel_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('parent_repository', 400);
            $table->boolean('inactive');
            $table->boolean('locked');
            $table->string('locked_by_name', 50)->nullable();
            $table->timestamp('locked_date')->nullable();
            $table->string('language_locked', 10)->nullable();
            $table->boolean('ui_freeze')->nullable();
            $table->string('comments', 500)->nullable();
            $table->boolean('allow_object_locking')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_projects');
    }
};
