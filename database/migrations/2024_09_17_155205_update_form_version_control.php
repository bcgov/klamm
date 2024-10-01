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
            $table->dropColumn('status');
        });
        Schema::table('form_versions', function (Blueprint $table) {
            $table->enum('status', ['draft', 'testing', 'archived', 'published'])->default('draft');
            $table->string('updater_name')->nullable();
            $table->string('updater_email')->nullable();
            $table->text('comments')->nullable();
            $table->enum('deployed_to', ['dev', 'test', 'prod'])->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->unique(['form_id', 'deployed_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn('updater_name');
            $table->dropColumn('updater_email');
            $table->dropColumn('comments');
            $table->dropColumn('deployed_to');
            $table->dropColumn('deployed_at');
            $table->dropUnique(['form_id', 'deployed_to']);
        });
    }
};
