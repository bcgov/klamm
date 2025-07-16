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
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            // Rename existing columns to match Carbon Design System
            $table->renameColumn('label', 'text');
            $table->renameColumn('button_type', 'kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('button_input_form_elements', function (Blueprint $table) {
            // Reverse the column renames
            $table->renameColumn('text', 'label');
            $table->renameColumn('kind', 'button_type');
        });
    }
};
