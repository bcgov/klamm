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
            // Drop the existing unique constraint on uuid
            $table->dropUnique(['uuid']);

            // Add a composite unique constraint on uuid and form_version_id
            $table->unique(['uuid', 'form_version_id'], 'form_elements_uuid_form_version_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique('form_elements_uuid_form_version_unique');

            // Restore the original unique constraint on uuid
            $table->unique('uuid');
        });
    }
};
