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
        Schema::table('increment', function (Blueprint $table) {
            Schema::table('form_versions', function (Blueprint $table) {
                $table->dropColumn('version_number');
            });
    
            Schema::table('form_versions', function (Blueprint $table) {
                $table->unsignedInteger('version_number')->nullable(false)->after('form_id');
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('increment', function (Blueprint $table) {
            Schema::table('form_versions', function (Blueprint $table) {
                $table->dropColumn('version_number');
            });
    
            Schema::table('form_versions', function (Blueprint $table) {
                $table->string('version_number')->nullable()->after('form_id');
            });
        });
    }
};
