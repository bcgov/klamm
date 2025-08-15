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
        // Pivot table to link scripts to multiple form versions
        Schema::create('form_script_form_version', function (Blueprint $table) {
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_script_id')->constrained('form_scripts')->onDelete('cascade');
            $table->primary(['form_version_id', 'form_script_id']);
            $table->timestamps(); // add timestamps for withTimestamps()
        });

        // Pivot table to link style sheets to multiple form versions
        Schema::create('style_sheet_form_version', function (Blueprint $table) {
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('style_sheet_id')->constrained('style_sheets')->onDelete('cascade');
            $table->primary(['form_version_id', 'style_sheet_id']);
            $table->timestamps(); // add timestamps for withTimestamps()
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot tables
        Schema::dropIfExists('form_script_form_version');
        Schema::dropIfExists('style_sheet_form_version');
    }
};
