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
        Schema::table('select_option_form_elements', function (Blueprint $table) {
            // Add the new value column
            $table->string('value')->nullable()->after('label');

            // Remove the description column
            $table->dropColumn('description');
        });

        // Populate value field with slugified label for existing records
        DB::table('select_option_form_elements')
            ->whereNull('value')
            ->orderBy('id')
            ->chunk(100, function ($options) {
                foreach ($options as $option) {
                    DB::table('select_option_form_elements')
                        ->where('id', $option->id)
                        ->update(['value' => \Illuminate\Support\Str::slug($option->label, '-')]);
                }
            });

        // Make value column not nullable
        Schema::table('select_option_form_elements', function (Blueprint $table) {
            $table->string('value')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('select_option_form_elements', function (Blueprint $table) {
            // Add back the description column
            $table->text('description')->nullable()->after('order');

            // Remove the value column
            $table->dropColumn('value');
        });
    }
};
