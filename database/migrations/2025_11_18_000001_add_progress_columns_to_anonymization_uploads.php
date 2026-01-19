<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            $table->string('status_detail')->nullable()->after('status');
            $table->timestamp('progress_updated_at')->nullable()->after('status_detail');
            $table->unsignedBigInteger('total_bytes')->nullable()->after('deleted');
            $table->unsignedBigInteger('processed_bytes')->default(0)->after('total_bytes');
            $table->unsignedBigInteger('processed_rows')->default(0)->after('processed_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            $table->dropColumn([
                'status_detail',
                'progress_updated_at',
                'total_bytes',
                'processed_bytes',
                'processed_rows',
            ]);
        });
    }
};
