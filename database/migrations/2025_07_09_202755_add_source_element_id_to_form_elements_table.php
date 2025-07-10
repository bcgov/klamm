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
        Schema::table('form_elements', function (Blueprint $table) {
            $table->unsignedBigInteger('source_element_id')->nullable()->after('is_template');
            $table->foreign('source_element_id')->references('id')->on('form_elements')->onDelete('set null');
            $table->index('source_element_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            $table->dropForeign(['source_element_id']);
            $table->dropIndex(['source_element_id']);
            $table->dropColumn('source_element_id');
        });
    }
};
