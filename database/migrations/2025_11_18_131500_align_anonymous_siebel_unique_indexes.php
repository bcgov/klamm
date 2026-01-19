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
        Schema::table('anonymous_siebel_databases', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymous_siebel_databases', 'database_name')) {
                return;
            }

            $table->unique('database_name', 'anonymous_siebel_databases_name_unique');
        });

        Schema::table('anonymous_siebel_schemas', function (Blueprint $table) {
            if (Schema::hasColumn('anonymous_siebel_schemas', 'schema_name')) {
                $table->dropUnique('anonymous_siebel_schemas_schema_name_unique');
                $table->unique(['database_id', 'schema_name'], 'anonymous_siebel_schemas_database_schema_unique');
            }
        });

        Schema::table('anonymous_siebel_data_types', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymous_siebel_data_types', 'data_type_name')) {
                return;
            }

            $table->unique('data_type_name', 'anonymous_siebel_data_types_name_unique');
        });

        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            if (! Schema::hasColumns('anonymous_siebel_stagings', ['upload_id', 'database_name', 'schema_name', 'table_name', 'column_name'])) {
                return;
            }

            $table->unique(
                ['upload_id', 'database_name', 'schema_name', 'table_name', 'column_name'],
                'anonymous_siebel_stagings_upload_schema_table_column_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anonymous_siebel_stagings', function (Blueprint $table) {
            $table->dropUnique('anonymous_siebel_stagings_upload_schema_table_column_unique');
        });

        Schema::table('anonymous_siebel_data_types', function (Blueprint $table) {
            $table->dropUnique('anonymous_siebel_data_types_name_unique');
        });

        Schema::table('anonymous_siebel_schemas', function (Blueprint $table) {
            $table->dropUnique('anonymous_siebel_schemas_database_schema_unique');
            $table->unique('schema_name', 'anonymous_siebel_schemas_schema_name_unique');
        });

        Schema::table('anonymous_siebel_databases', function (Blueprint $table) {
            $table->dropUnique('anonymous_siebel_databases_name_unique');
        });
    }
};
