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
        Schema::table('reports', function (Blueprint $table) {
            $table->text('description')->nullable();
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->enum('follow_up_required', ['cgi', 'fasb', 'mis', 'mis/fasb', 'no', 'opc', 'pending_mis', 'tbd'])->default('tbd');
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->dropColumn('data_matching_rate');
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->enum('data_matching_rate', ['low', 'medium', 'high', 'n/a'])->default('n/a');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->dropColumn('follow_up_required');
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->dropColumn('data_matching_rate');
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->enum('data_matching_rate', ['low', 'medium', 'high'])->nullable();
        });
    }
};
