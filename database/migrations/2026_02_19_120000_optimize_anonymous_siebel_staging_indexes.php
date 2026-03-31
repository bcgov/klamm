<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL CREATE/DROP INDEX CONCURRENTLY cannot run inside a transaction.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE anonymous_siebel_stagings DROP CONSTRAINT IF EXISTS anonymous_siebel_stagings_upload_schema_table_column_unique');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS anonymous_siebel_stagings_upload_schema_table_column_unique');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS anonymous_siebel_stagings_upload_id_index');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS staging_lookup');

        DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS anonymous_siebel_stagings_unique_column ON anonymous_siebel_stagings (upload_id, database_name, schema_name, table_name, column_name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS anonymous_siebel_stagings_upload_id_index ON anonymous_siebel_stagings (upload_id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS staging_lookup ON anonymous_siebel_stagings (database_name, schema_name, object_type, table_name, column_name)');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'anonymous_siebel_stagings_upload_schema_table_column_unique'
          AND conrelid = 'anonymous_siebel_stagings'::regclass
    ) THEN
        ALTER TABLE anonymous_siebel_stagings
            ADD CONSTRAINT anonymous_siebel_stagings_upload_schema_table_column_unique
            UNIQUE (upload_id, database_name, schema_name, table_name, column_name);
    END IF;
END
$$;
SQL);
    }
};
