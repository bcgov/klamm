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
        Schema::dropIfExists('boundary_system_file_field_maps');
        Schema::dropIfExists('boundary_system_file_field_map_sections');
        Schema::dropIfExists('boundary_system_file_fields');
        Schema::dropIfExists('boundary_system_file_field_types');
        Schema::dropIfExists('boundary_system_files');
        Schema::dropIfExists('boundary_system_file_separators');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a one time deletion of previous changes, we should not roll this back
    }
};
