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
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->string('custom_id')->nullable();
            $table->unique(['form_version_id', 'custom_id'], 'form_version_custom_id_group_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->dropUnique('form_version_custom_id_group_unique');
            $table->dropColumn('custom_id');
        });
    }
};
