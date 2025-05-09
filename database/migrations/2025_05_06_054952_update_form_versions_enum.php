<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // add a temp column for the new enum statuses
        Schema::table('form_versions', function (Blueprint $table) {
            $table->enum('temp_status', ['draft', 'under_review', 'approved', 'published', 'archived'])->default('draft');
        });

        // copy the existing values to the new column
        DB::table('form_versions')->get()->each(function ($record) {
            $newStatus = match ($record->status) {
                'draft' => 'draft',
                'testing' => 'under_review',
                'published' => 'published',
                'archived' => 'archived',
                default => 'draft',
            };

            DB::table('form_versions')
                ->where('id', $record->id)
                ->update(['temp_status' => $newStatus]);
        });

        // delete the old column
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // rename the temp column
        Schema::table('form_versions', function (Blueprint $table) {
            $table->renameColumn('temp_status', 'status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // add a temp column for the old enum statuses
        Schema::table('form_versions', function (Blueprint $table) {
            $table->enum('temp_status', ['draft', 'testing', 'published', 'archived'])->default('draft');
        });

        // copy the existing values back to the original format
        DB::table('form_versions')->get()->each(function ($record) {
            $oldStatus = match ($record->status) {
                'draft' => 'draft',
                'under_review' => 'testing',
                'published' => 'published',
                'archived' => 'archived',
                default => 'draft',
            };

            DB::table('form_versions')
                ->where('id', $record->id)
                ->update(['temp_status' => $oldStatus]);
        });

        // delete the new column
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        // rename the temp column back to the original name
        Schema::table('form_versions', function (Blueprint $table) {
            $table->renameColumn('temp_status', 'status');
        });
    }
};
