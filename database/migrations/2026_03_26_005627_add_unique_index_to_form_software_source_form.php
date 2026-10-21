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
        Schema::table('form_software_source_form', function (Blueprint $table) {
            $table->unique(
                ['form_id', 'form_software_source_id'],
                'form_software_source_form_unique'
            );
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_software_source_form', function (Blueprint $table) {
            $table->dropUnique('form_software_source_form_unique');
        });
    }
};
