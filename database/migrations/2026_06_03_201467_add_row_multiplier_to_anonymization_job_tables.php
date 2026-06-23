<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            // Per-table row volume multiplier (order-of-magnitude data expansion).
            // Default 1 keeps existing job output byte-for-byte unchanged.
            $table->unsignedInteger('row_multiplier')->default(1)->after('table_id');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            $table->dropColumn('row_multiplier');
        });
    }
};
