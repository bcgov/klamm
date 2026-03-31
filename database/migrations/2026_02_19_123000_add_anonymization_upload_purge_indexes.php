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

        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS anonymization_uploads_status_updated_id_idx ON anonymization_uploads (status, updated_at, id)');
        DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS anonymization_uploads_file_retention_id_idx ON anonymization_uploads (file_deleted_at, retention_until, id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS anonymization_uploads_status_updated_id_idx');
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS anonymization_uploads_file_retention_id_idx');
    }
};
