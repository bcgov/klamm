<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_methods', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_methods', 'version_root_id')) {
                $table->unsignedBigInteger('version_root_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('anonymization_methods', 'supersedes_id')) {
                $table->unsignedBigInteger('supersedes_id')->nullable()->after('version_root_id');
            }

            if (! Schema::hasColumn('anonymization_methods', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('supersedes_id');
            }

            if (! Schema::hasColumn('anonymization_methods', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('version');
            }

            $table->index(['version_root_id', 'version']);
            $table->index(['version_root_id', 'is_current']);
        });

        Schema::table('anonymization_packages', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_packages', 'version_root_id')) {
                $table->unsignedBigInteger('version_root_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('anonymization_packages', 'supersedes_id')) {
                $table->unsignedBigInteger('supersedes_id')->nullable()->after('version_root_id');
            }

            if (! Schema::hasColumn('anonymization_packages', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('supersedes_id');
            }

            if (! Schema::hasColumn('anonymization_packages', 'is_current')) {
                $table->boolean('is_current')->default(true)->after('version');
            }

            $table->index(['version_root_id', 'version']);
            $table->index(['version_root_id', 'is_current']);
        });

        // Backfill existing rows so they form a version group anchored by themselves.
        DB::table('anonymization_methods')
            ->whereNull('version_root_id')
            ->update([
                'version_root_id' => DB::raw('id'),
                'version' => 1,
                'is_current' => true,
            ]);

        DB::table('anonymization_packages')
            ->whereNull('version_root_id')
            ->update([
                'version_root_id' => DB::raw('id'),
                'version' => 1,
                'is_current' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('anonymization_methods', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_methods', 'is_current')) {
                $table->dropColumn('is_current');
            }

            if (Schema::hasColumn('anonymization_methods', 'version')) {
                $table->dropColumn('version');
            }

            if (Schema::hasColumn('anonymization_methods', 'supersedes_id')) {
                $table->dropColumn('supersedes_id');
            }

            if (Schema::hasColumn('anonymization_methods', 'version_root_id')) {
                $table->dropColumn('version_root_id');
            }
        });

        Schema::table('anonymization_packages', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_packages', 'is_current')) {
                $table->dropColumn('is_current');
            }

            if (Schema::hasColumn('anonymization_packages', 'version')) {
                $table->dropColumn('version');
            }

            if (Schema::hasColumn('anonymization_packages', 'supersedes_id')) {
                $table->dropColumn('supersedes_id');
            }

            if (Schema::hasColumn('anonymization_packages', 'version_root_id')) {
                $table->dropColumn('version_root_id');
            }
        });
    }
};
