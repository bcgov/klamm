<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            // Optional manual override for the schema where the generated script executes.
            // If null/blank, Klamm falls back to a job-type-derived default.
            $table->string('target_schema')->nullable()->after('output_format');
            $table->index('target_schema');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropIndex(['target_schema']);
            $table->dropColumn(['target_schema']);
        });
    }
};
