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
        Schema::create('anonymous_siebel_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')->constrained('anonymization_uploads')->cascadeOnDelete();
            $table->string('database_name', 128);
            $table->string('schema_name', 128);
            $table->string('object_type', 64);
            $table->string('table_name', 256);
            $table->string('column_name', 256);
            $table->unsignedInteger('column_id')->nullable();
            $table->string('data_type', 128)->nullable();
            $table->unsignedInteger('data_length')->nullable();
            $table->unsignedInteger('data_precision')->nullable();
            $table->unsignedInteger('data_scale')->nullable();
            $table->string('nullable', 8)->nullable();
            $table->unsignedInteger('char_length')->nullable();
            $table->text('column_comment')->nullable();
            $table->text('table_comment')->nullable();
            $table->text('related_columns_raw')->nullable();
            $table->json('related_columns')->nullable();
            $table->string('content_hash', 64);
            $table->timestamps();

            $table->index(['upload_id']);
            $table->index(['database_name', 'schema_name', 'object_type', 'table_name', 'column_name'], 'staging_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anonymous_siebel_staging');
    }
};
