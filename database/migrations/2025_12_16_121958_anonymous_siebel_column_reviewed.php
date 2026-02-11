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
        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->boolean('anonymization_requirement_reviewed')->nullable()->default(null);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->dropColumn('anonymization_requirement_reviewed');
        });
    }
};
