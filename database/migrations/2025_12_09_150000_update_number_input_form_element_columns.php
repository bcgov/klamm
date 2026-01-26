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
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            // Add new mask columns
            $table->string('maskType')->default('integer')->after('step');
            $table->string('mask')->nullable()->after('maskType');
        });

        // If the old column exists, copy data from formatStyle to maskType and then drop it
        if (Schema::hasColumn('number_input_form_elements', 'formatStyle')) {
            DB::table('number_input_form_elements')->update([
                'maskType' => DB::raw('COALESCE("formatStyle", \'integer\')')
            ]);

            Schema::table('number_input_form_elements', function (Blueprint $table) {
                $table->dropColumn('formatStyle');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            // Restore the old formatStyle column as an enum
            $table->enum('formatStyle', ['decimal', 'currency', 'integer'])->default('decimal')->after('step');
        });

        // If maskType exists, copy back valid values into formatStyle
        if (Schema::hasColumn('number_input_form_elements', 'maskType')) {
            DB::table('number_input_form_elements')
                ->whereIn('maskType', ['decimal', 'currency', 'integer'])
                ->update([
                    'formatStyle' => DB::raw('"maskType"')
                ]);
        }

        // Remove the new columns
        Schema::table('number_input_form_elements', function (Blueprint $table) {
            $table->dropColumn(['maskType', 'mask']);
        });
    }
};
