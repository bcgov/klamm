<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_binding_mappings', function (Blueprint $table) {
            $table->id();

            // Required fields
            $table->string('label');         // human readable title
            $table->string('data_source');   // e.g. Contact
            $table->string('path_label');    // e.g. First Name

            // Optional fields
            $table->text('description')->nullable();
            $table->string('endpoint')->nullable();        // ICM endpoint (free text)
            $table->string('repeating_path')->nullable();  // JSONPath for repeaters

            // Convenience (read-only in UI)
            $table->string('data_path');     // $.['{data_source}'].['{path_label}']

            $table->json('meta')->nullable();
            $table->timestamps();

            // Helpful indexes
            $table->index('data_source');

            if (DB::getDriverName() !== 'pgsql') {
                $table->unique(['data_source', 'path_label'], 'dbm_source_label_unique_fallback');
            }
        });

        // Postgres: create a functional unique index for case-insensitive uniqueness.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX data_binding_mappings_source_label_lower_unique
                ON data_binding_mappings (LOWER(data_source), LOWER(path_label));
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('data_binding_mappings')) {
            // Drop the functional index in Postgres if it exists
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS data_binding_mappings_source_label_lower_unique;');
            }

            Schema::dropIfExists('data_binding_mappings');
        }
    }
};
