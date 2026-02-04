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
        Schema::create('anonymous_siebel_databases', function (Blueprint $table) {
            $table->id();
            $table->string('database_name', 256)->unique();
            $table->text('description')->nullable();
            $table->string('content_hash', 64); // sha256 of normalized content
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('changed_fields')->nullable(); // field-level diffs on change
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_databases');
    }
};
