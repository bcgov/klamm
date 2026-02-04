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
        Schema::create('anonymous_siebel_tables', function (Blueprint $table) {
            $table->id();
            $table->enum('object_type', ['table', 'view']);
            $table->string('table_name', 256);
            $table->text('table_comment')->nullable();
            $table->string('content_hash', 64); // sha256 of normalized content
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('changed_fields')->nullable(); // field-level diffs on change
            $table->softDeletes();
            $table->timestamps();

            $table->foreignId('schema_id')->nullable()->constrained('anonymous_siebel_schemas');

            $table->unique(['schema_id', 'table_name']);
            $table->index('table_name');
        });
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_tables');
        //
    }
};
