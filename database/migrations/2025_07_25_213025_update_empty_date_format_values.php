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
        // Update any DateSelectInputFormElement records with NULL or empty dateFormat
        // to use 'YYYY-MMM-DD' as the default
        DB::table('date_select_input_form_elements')
            ->whereNull('dateFormat')
            ->orWhere('dateFormat', '')
            ->update(['dateFormat' => 'YYYY-MMM-DD']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration doesn't need a rollback since we're just setting defaults
        // and the model already has 'YYYY-MMM-DD' as the default attribute
    }
};
