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
        Schema::table('container_form_elements', function (Blueprint $table) {
            // Add a nullable string column for label_level (e.g., 'h1', 'h2', etc)
            $table->string('level', 8)->nullable()->after('container_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
