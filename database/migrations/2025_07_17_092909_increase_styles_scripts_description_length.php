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
        // Change description column to text for longer content
        Schema::table('form_scripts', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });

        Schema::table('style_sheets', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert description column back to string(255)
        Schema::table('form_scripts', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->change();
        });

        Schema::table('style_sheets', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->change();
        });
    }
};
