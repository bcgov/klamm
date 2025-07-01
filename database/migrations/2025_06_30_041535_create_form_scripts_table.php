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
        Schema::create('form_scripts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->string('filename');
            $table->enum('type', ['web', 'pdf']);
            $table->timestamps();

            $table->unique(['form_version_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_scripts');
    }
};
