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
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->string('maskType')->nullable()->after('hideLabel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('text_input_form_elements', function (Blueprint $table) {
            $table->dropColumn('maskType');
        });
    }
};
