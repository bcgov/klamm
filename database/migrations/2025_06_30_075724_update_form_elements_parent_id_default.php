<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['parent_id']);
        });

        // Update existing null parent_id values to -1
        DB::table('form_elements')->whereNull('parent_id')->update(['parent_id' => -1]);

        Schema::table('form_elements', function (Blueprint $table) {
            // Change the column to default to -1 instead of null
            $table->unsignedBigInteger('parent_id')->default(-1)->change();

            // Add back the foreign key constraint but allow -1 as a special case
            // We'll add a check constraint instead that allows either -1 or valid IDs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert -1 values back to null first
        DB::table('form_elements')->where('parent_id', -1)->update(['parent_id' => null]);

        Schema::table('form_elements', function (Blueprint $table) {
            // Revert back to nullable
            $table->unsignedBigInteger('parent_id')->nullable()->change();

            // Add back the original foreign key constraint
            $table->foreign('parent_id')->references('id')->on('form_elements')->onDelete('cascade');
        });
    }
};
