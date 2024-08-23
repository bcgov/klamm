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
        Schema::table('forms', function (Blueprint $table) {
            $table->string('print_reason')->nullable()->after('form_reach_id');
            $table->integer('retention_needs')->nullable()->after('print_reason');
            $table->boolean('icm_non_interactive')->nullable()->after('retention_needs');
            $table->string('footer_fragment_path')->nullable()->after('icm_non_interactive');
            $table->char('dcv_material_number', 10)->nullable()->after('footer_fragment_path');
            $table->string('orbeon_functions')->nullable()->after('dcv_material_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn([
                'print_reason',
                'retention_needs',
                'icm_non_interactive',
                'footer_fragment_path',
                'dcv_material_number',
                'orbeon_functions',
            ]);
        });
    }
};
