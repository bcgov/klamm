<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            DELETE FROM form_software_source_form a
            USING form_software_source_form b
            WHERE a.id > b.id
            AND a.form_id = b.form_id
            AND a.form_software_source_id = b.form_software_source_id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // no-op (duplicates cannot be reliably restored)
    }
};
