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
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn(['updater_name', 'updater_email']);

            $table->foreignId('form_developer_id')->nullable()->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->string('updater_name')->nullable();
            $table->string('updater_email')->nullable();

            $table->dropForeign(['form_developer_id']);
            $table->dropColumn('form_developer_id');
        });
    }
};
