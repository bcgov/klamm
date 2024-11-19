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
        DB::statement("
            CREATE VIEW system_messages_view AS
            SELECT
                CONCAT('MIS-', id) as id,
                'MISIntegrationError' as type,
                message_copy,
                fix,
                explanation
            FROM mis_integration_errors
            UNION ALL
            SELECT
                CONCAT('ICMErr-', id) as id,
                'ICMErrorMessage' as type,
                message_copy,
                fix,
                explanation
            FROM icm_error_messages
            UNION ALL
            SELECT
                CONCAT('ICMSys-', id) as id,
                'ICMSystemMessage' as type,
                message_copy,
                fix,
                explanation
            FROM icm_system_messages
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS system_messages_view");
    }
};
