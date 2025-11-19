<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            $table->unique(
                ['upload_id', 'database_name', 'schema_name', 'table_name', 'column_name'],
                'anonymous_siebel_stagings_unique_column'
            );
        });

        Schema::table('anonymous_siebel_tables', function (Blueprint $table) {
            $table->index(['schema_id', 'table_name'], 'anonymous_siebel_tables_schema_lookup');
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->index(['table_id', 'column_name'], 'anonymous_siebel_columns_table_lookup');
            $table->index(['last_synced_at'], 'anonymous_siebel_columns_last_sync');
        });
    }

    public function down(): void
    {
        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            $table->dropUnique('anonymous_siebel_stagings_unique_column');
        });

        Schema::table('anonymous_siebel_tables', function (Blueprint $table) {
            $table->dropIndex('anonymous_siebel_tables_schema_lookup');
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->dropIndex('anonymous_siebel_columns_table_lookup');
            $table->dropIndex('anonymous_siebel_columns_last_sync');
        });
    }
};
