<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $columns = [
            'visible_web',
            'visible_pdf',
            'is_required',
            'is_read_only',
        ];

        // Update all null or false values to 'never' for the specified columns
        foreach ($columns as $col) {
            DB::table('form_elements')
                ->where(function ($query) use ($col) {
                    $query->whereNull($col)
                        ->orWhere($col, false)
                        ->orWhere($col, 'false');
                })
                ->update([$col => 'never']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columns = [
            'visible_web',
            'visible_pdf',
            'is_required',
            'is_read_only',
        ];

        // Revert 'never' values back to null for the specified columns
        foreach ($columns as $col) {
            DB::table('form_elements')
                ->where($col, 'never')
                ->update([$col => null]);
        }
    }
};