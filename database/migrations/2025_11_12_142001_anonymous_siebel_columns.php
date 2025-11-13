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
        Schema::create('anonymous_siebel_columns', function (Blueprint $table) {
            $table->id();
            $table->string('column_name', 256);
            $table->unsignedInteger('column_id')->nullable();
            $table->unsignedInteger('data_length')->nullable();
            $table->unsignedInteger('data_precision')->nullable();
            $table->unsignedInteger('data_scale')->nullable();
            $table->boolean('nullable')->nullable();
            $table->unsignedInteger('char_length')->nullable();
            $table->text('column_comment')->nullable();
            $table->text('table_comment')->nullable();
            $table->text('related_columns_raw')->nullable();
            $table->json('related_columns')->nullable(); // parsed structure if you choose to parse
            $table->string('content_hash', 64); // sha256 of normalized content
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('changed_fields')->nullable(); // field-level diffs on change
            $table->softDeletes();
            $table->timestamps();


            //relationships
            $table->foreignId('table_id')->nullable()->constrained('anonymous_siebel_tables');
            $table->foreignId('data_type_id')->nullable()->constrained('anonymous_siebel_data_types');

            $table->unique(['table_id', 'column_name']);
            $table->index('column_name');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_columns');
    }
};
