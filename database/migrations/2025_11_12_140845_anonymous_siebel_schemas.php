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
        Schema::create('anonymous_siebel_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('schema_name', 256)->unique();
            $table->text('description')->nullable();
            $table->string('type', 64)->nullable();
            $table->string('content_hash', 64); // sha256 of normalized content
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('changed_fields')->nullable(); // field-level diffs on change
            $table->softDeletes();
            $table->timestamps();

            //relationships
            $table->foreignId('database_id')->nullable()->constrained('anonymous_siebel_databases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_schemas');
    }
};
