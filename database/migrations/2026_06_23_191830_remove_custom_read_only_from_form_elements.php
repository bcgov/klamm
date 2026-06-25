<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            $table->dropColumn('custom_read_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            // Restoring as a nullable text column since it previously held JS scripts
            $table->text('custom_read_only')->nullable()->after('is_read_only');
        });
    }
};