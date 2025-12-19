<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_uploads', 'run_phase')) {
                $table->string('run_phase')->nullable()->after('status_detail');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'checkpoint')) {
                $table->json('checkpoint')->nullable()->after('run_phase');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'failed_phase')) {
                $table->string('failed_phase')->nullable()->after('checkpoint');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'error_context')) {
                $table->json('error_context')->nullable()->after('error');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'warnings_count')) {
                $table->unsignedInteger('warnings_count')->default(0)->after('error_context');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'warnings')) {
                $table->json('warnings')->nullable()->after('warnings_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            foreach (['warnings', 'warnings_count', 'error_context', 'failed_phase', 'checkpoint', 'run_phase'] as $col) {
                if (Schema::hasColumn('anonymization_uploads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
