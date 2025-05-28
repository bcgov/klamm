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
        Schema::table('form_versions', function (Blueprint $table) {
            $table->string('pdf_template_name')->nullable();
            $table->string('pdf_template_version')->nullable();
            $table->text('pdf_template_parameters')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn('pdf_template_name')->nullable();
            $table->dropColumn('pdf_template_version')->nullable();
            $table->dropColumn('pdf_template_parameters')->nullable();
        });
    }
};
