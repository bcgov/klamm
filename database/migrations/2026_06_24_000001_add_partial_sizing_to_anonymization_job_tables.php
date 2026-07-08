<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            $table->string('volume_direction', 32)->default('expand')->after('target_row_count');
            $table->string('reduction_strategy', 64)->nullable()->after('volume_direction');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_job_tables', function (Blueprint $table) {
            $table->dropColumn(['volume_direction', 'reduction_strategy']);
        });
    }
};
