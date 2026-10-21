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
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->integer('min_repeats')->nullable()->after('repeater_item_label');
            $table->integer('max_repeats')->nullable()->after('min_repeats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('container_form_elements', function (Blueprint $table) {
            $table->dropColumn(['min_repeats', 'max_repeats']);
        });
    }
};
