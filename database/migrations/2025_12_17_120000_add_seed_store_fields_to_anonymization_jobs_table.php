<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->string('seed_store_mode')->default('temporary')->after('output_format');
            $table->string('seed_store_schema')->nullable()->after('seed_store_mode');
            $table->string('seed_store_prefix')->nullable()->after('seed_store_schema');
            $table->string('job_seed')->nullable()->after('seed_store_prefix');
            $table->longText('pre_mask_sql')->nullable()->after('job_seed');
            $table->longText('post_mask_sql')->nullable()->after('pre_mask_sql');

            $table->index('seed_store_mode');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropIndex(['seed_store_mode']);
            $table->dropColumn([
                'seed_store_mode',
                'seed_store_schema',
                'seed_store_prefix',
                'job_seed',
                'pre_mask_sql',
                'post_mask_sql',
            ]);
        });
    }
};
