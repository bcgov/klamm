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
        Schema::table('rendered_forms', function (Blueprint $table) {
            $table->foreignId('ministry_id')->nullable()->constrained('ministries')->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rendered_forms', function (Blueprint $table) {
            $table->dropForeign(['ministry_id']);
            $table->dropColumn('ministry_id');
        });
    }
};
