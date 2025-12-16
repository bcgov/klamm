<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_uploads', 'retention_until')) {
                $table->timestamp('retention_until')->nullable()->after('progress_updated_at');
            }

            if (! Schema::hasColumn('anonymization_uploads', 'file_deleted_at')) {
                $table->timestamp('file_deleted_at')->nullable()->after('retention_until');
            }

            if (! Schema::hasColumn('anonymization_uploads', 'file_deleted_reason')) {
                $table->string('file_deleted_reason', 50)->nullable()->after('file_deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_uploads', 'file_deleted_reason')) {
                $table->dropColumn('file_deleted_reason');
            }
            if (Schema::hasColumn('anonymization_uploads', 'file_deleted_at')) {
                $table->dropColumn('file_deleted_at');
            }
            if (Schema::hasColumn('anonymization_uploads', 'retention_until')) {
                $table->dropColumn('retention_until');
            }
        });
    }
};
