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
        Schema::create('form_elements', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            // Foreign key for parent_id (self-referencing)
            $table->foreign('parent_id')->references('id')->on('form_elements')->onDelete('cascade');

            // Index for better performance
            $table->index(['form_version_id', 'order']);
            $table->index('parent_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_elements');
    }
};
