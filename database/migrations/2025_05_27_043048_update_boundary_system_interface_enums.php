<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('boundary_system_interfaces', function (Blueprint $table) {
            $table->dropColumn('transaction_frequency');
            $table->dropColumn('mode_of_transfer');
            $table->dropColumn('data_format');
            $table->dropColumn('transaction_schedule');
        });

        Schema::table('boundary_system_interfaces', function (Blueprint $table) {
            $table->enum('transaction_frequency', [
                'daily',
                'monthly',
                'annually',
                'hourly',
                'on_demand',
                'weekly',
                'multiple_times_a_day',
                'quarterly',
                'custom',
                'other'
            ])->nullable()->after('target_system_id');

            $table->enum('mode_of_transfer', [
                'batch',
                'file_transfer',
                'appgate',
                'real_time',
                'vbc_real_time_sync',
                'email',
                'lin'
            ])->after('integration_type');

            $table->json('data_format')->after('protocol');

            $table->string('transaction_schedule', length: 512)->nullable()->after('transaction_frequency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boundary_system_interfaces', function (Blueprint $table) {
            $table->dropColumn('transaction_frequency');
            $table->dropColumn('mode_of_transfer');
            $table->dropColumn('data_format');
            $table->dropColumn('transaction_schedule');
        });

        Schema::table('boundary_system_interfaces', function (Blueprint $table) {
            $table->enum('transaction_frequency', [
                'on_demand',
                'hourly',
                'multiple_times_a_day',
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'annually',
                'custom'
            ])->nullable()->after('target_system_id');

            $table->enum('mode_of_transfer', [
                'batch',
                'asynchronous',
                'synchronous',
                'real_time',
                'near_real_time',
                'unknown'
            ])->after('integration_type');

            $table->json('data_format')->after('protocol');

            $table->string('transaction_schedule')->nullable()->after('transaction_frequency');
        });
    }
};
