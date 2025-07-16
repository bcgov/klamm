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
        // Allow scripts to be created without a form_version_id
        Schema::table('form_scripts', function (Blueprint $table) {
            $table->unsignedBigInteger('form_version_id')->nullable()->change();
            $table->string('description')->nullable()->after('filename');
        });

        // Drop old check constraint and add new one for 'type'
        DB::statement("ALTER TABLE form_scripts DROP CONSTRAINT IF EXISTS form_scripts_type_check");
        DB::statement("ALTER TABLE form_scripts ADD CONSTRAINT form_scripts_type_check CHECK (type IN ('web', 'pdf', 'template'))");

        // Allow stylesheets to be created without a form_version_id
        Schema::table('style_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('form_version_id')->nullable()->change();
            $table->string('description')->nullable()->after('filename');
        });

        // Drop old check constraint and add new one for 'type' on style_sheets
        DB::statement("ALTER TABLE style_sheets DROP CONSTRAINT IF EXISTS style_sheets_type_check");
        DB::statement("ALTER TABLE style_sheets ADD CONSTRAINT style_sheets_type_check CHECK (type IN ('web', 'pdf', 'template'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert scripts to require form_version_id
        Schema::table('form_scripts', function (Blueprint $table) {
            $table->unsignedBigInteger('form_version_id')->nullable(false)->change();
            $table->dropColumn('description');
        });

        // Revert 'type' check constraint to only 'web' and 'pdf'
        DB::statement("ALTER TABLE form_scripts DROP CONSTRAINT IF EXISTS form_scripts_type_check");
        DB::statement("ALTER TABLE form_scripts ADD CONSTRAINT form_scripts_type_check CHECK (type IN ('web', 'pdf'))");

        // Revert stylesheets to require form_version_id
        Schema::table('style_sheets', function (Blueprint $table) {
            $table->unsignedBigInteger('form_version_id')->nullable(false)->change();
            $table->dropColumn('description');
        });

        // Revert 'type' check constraint to only 'web' and 'pdf' on style_sheets
        DB::statement("ALTER TABLE style_sheets DROP CONSTRAINT IF EXISTS style_sheets_type_check");
        DB::statement("ALTER TABLE style_sheets ADD CONSTRAINT style_sheets_type_check CHECK (type IN ('web', 'pdf'))");
    }
};
