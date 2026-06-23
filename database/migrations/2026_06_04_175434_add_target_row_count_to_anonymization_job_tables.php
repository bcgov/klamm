<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            $table->string('volume_mode', 32)->default('multiplier')->after('row_multiplier');
            $table->unsignedBigInteger('target_row_count')->nullable()->after('volume_mode');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            $table->dropColumn(['volume_mode', 'target_row_count']);
        });
    }
};
