<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // Drop existing FK (if any)
            $constraint = DB::selectOne("
                SELECT conname
                FROM pg_constraint c
                JOIN pg_class t ON c.conrelid = t.oid
                JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY (c.conkey)
                WHERE t.relname = 'form_elements'
                  AND a.attname = 'form_version_id'
                  AND c.contype = 'f'
                LIMIT 1
            ");

            if ($constraint?->conname) {
                DB::statement("ALTER TABLE form_elements DROP CONSTRAINT {$constraint->conname}");
            }

            // Make column nullable
            DB::statement("ALTER TABLE form_elements ALTER COLUMN form_version_id DROP NOT NULL");

            // Add FK with NOT VALID
            DB::statement("
                ALTER TABLE form_elements
                ADD CONSTRAINT form_elements_form_version_id_fk
                FOREIGN KEY (form_version_id)
                REFERENCES form_versions(id)
                ON DELETE SET NULL
                NOT VALID
            ");

            // Validate constraint
            DB::statement("
                ALTER TABLE form_elements
                VALIDATE CONSTRAINT form_elements_form_version_id_fk
            ");
        } else {
            // MySQL / MariaDB
            $fk = DB::selectOne("
                SELECT CONSTRAINT_NAME AS name
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'form_elements'
                  AND COLUMN_NAME = 'form_version_id'
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                LIMIT 1
            ");

            if ($fk?->name) {
                DB::statement("ALTER TABLE form_elements DROP FOREIGN KEY `{$fk->name}`");
            }

            DB::statement("ALTER TABLE form_elements MODIFY form_version_id BIGINT NULL");

            DB::statement("
                ALTER TABLE form_elements
                ADD CONSTRAINT form_elements_form_version_id_fk
                FOREIGN KEY (form_version_id) REFERENCES form_versions(id)
                ON DELETE SET NULL
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE form_elements DROP CONSTRAINT IF EXISTS form_elements_form_version_id_fk");
            // NOTE: will fail if NULLs exist.
            DB::statement("ALTER TABLE form_elements ALTER COLUMN form_version_id SET NOT NULL");
            DB::statement("
                ALTER TABLE form_elements
                ADD CONSTRAINT form_elements_form_version_id_fk
                FOREIGN KEY (form_version_id)
                REFERENCES form_versions(id)
                ON DELETE RESTRICT
            ");
        } else {
            DB::statement("ALTER TABLE form_elements DROP FOREIGN KEY IF EXISTS form_elements_form_version_id_fk");
            DB::statement("ALTER TABLE form_elements MODIFY form_version_id BIGINT NOT NULL");
            DB::statement("
                ALTER TABLE form_elements
                ADD CONSTRAINT form_elements_form_version_id_fk
                FOREIGN KEY (form_version_id) REFERENCES form_versions(id)
                ON DELETE RESTRICT
            ");
        }
    }
};
