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
        if (!$this->indexExists('bre_rules', 'bre_rules_name_unique')) {
            Schema::table('bre_rules', function (Blueprint $table) {
                $table->unique('name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bre_rules', function (Blueprint $table) {
            if ($this->indexExists('bre_rules', 'bre_rules_name_unique')) {
                $table->dropUnique(['name']);
            }
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
