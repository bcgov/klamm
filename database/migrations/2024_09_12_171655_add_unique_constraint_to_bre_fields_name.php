<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if the unique constraint already exists before adding it
        if (!$this->indexExists('bre_fields', 'bre_fields_name_unique')) {
            Schema::table('bre_fields', function (Blueprint $table) {
                $table->unique('name');
            });
        }

        Schema::table('bre_fields', function (Blueprint $table) {
            $table->dropForeign(['data_type_id']);
            $table->foreign('data_type_id')->references('id')->on('bre_data_types');
        });

        Schema::table('bre_data_types', function (Blueprint $table) {
            $table->dropForeign(['value_type_id']);
            $table->foreign('value_type_id')->references('id')->on('bre_value_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bre_data_types', function (Blueprint $table) {
            $table->dropForeign(['value_type_id']);
            $table->foreign('value_type_id')->references('id')->on('value_types');
        });

        Schema::table('bre_fields', function (Blueprint $table) {
            if ($this->indexExists('bre_fields', 'bre_fields_name_unique')) {
                $table->dropUnique(['name']);
            }
            $table->dropForeign(['data_type_id']);
            $table->foreign('data_type_id')->references('id')->on('data_types');
        });
    }

    /**
     * Helper function to check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        return DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [$table, $indexName]) ? true : false;
    }
};
