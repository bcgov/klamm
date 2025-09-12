<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * This migration runs raw CREATE INDEX statements; Postgres will reject them
     * inside a transaction. Leave this untyped & public for Laravel 11.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        $driver = DB::getDriverName();

        // Keep only the single-column index on data_source.
        if ($driver === 'pgsql') {
            DB::statement("
                CREATE INDEX IF NOT EXISTS data_binding_mappings_data_source_idx
                ON data_binding_mappings (data_source)
            ");
        } else {
            DB::statement("
                CREATE INDEX data_binding_mappings_data_source_idx
                ON data_binding_mappings (data_source)
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS data_binding_mappings_data_source_idx');
        } else {
            DB::statement('DROP INDEX data_binding_mappings_data_source_idx ON data_binding_mappings');
        }
    }
};
