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
        // First, remove the collapsible-related columns
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->dropColumn(['collapsible', 'collapsed_by_default']);
        });

        // Update the container_type enum to include header and footer
        // For PostgreSQL, we need to drop and recreate the check constraint

        // Drop the existing check constraint
        DB::statement("ALTER TABLE container_form_elements DROP CONSTRAINT IF EXISTS container_form_elements_container_type_check");

        // Add new check constraint with additional values
        DB::statement("ALTER TABLE container_form_elements ADD CONSTRAINT container_form_elements_container_type_check CHECK (container_type IN ('page', 'fieldset', 'section', 'header', 'footer'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, handle any header/footer types by converting them to section
        DB::table('container_form_elements')
            ->whereIn('container_type', ['header', 'footer'])
            ->update(['container_type' => 'section']);

        // Revert the enum back to original values

        // Drop the current check constraint
        DB::statement("ALTER TABLE container_form_elements DROP CONSTRAINT IF EXISTS container_form_elements_container_type_check");

        // Add back the original check constraint
        DB::statement("ALTER TABLE container_form_elements ADD CONSTRAINT container_form_elements_container_type_check CHECK (container_type IN ('page', 'fieldset', 'section'))");


        // Add back the collapsible columns
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->boolean('collapsible')->default(false);
            $table->boolean('collapsed_by_default')->default(false);
        });
    }
};
