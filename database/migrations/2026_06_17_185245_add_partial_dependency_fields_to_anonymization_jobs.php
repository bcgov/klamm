<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->boolean('partial_uses_existing_full_anonymization')
                ->default(false)
                ->after('post_mask_sql');
            $table->text('partial_baseline_reference')
                ->nullable()
                ->after('partial_uses_existing_full_anonymization');
            $table->string('dependency_resolution_mode', 64)
                ->nullable()
                ->after('partial_baseline_reference');
            $table->json('dependency_resolution_metadata')
                ->nullable()
                ->after('dependency_resolution_mode');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'partial_uses_existing_full_anonymization',
                'partial_baseline_reference',
                'dependency_resolution_mode',
                'dependency_resolution_metadata',
            ]);
        });
    }
};
