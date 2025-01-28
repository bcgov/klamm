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
        Schema::table('field_groups', function ($table) {
            $table->string('repeater_item_label')->nullable();
        });

        Schema::table('field_group_instances', function ($table) {
            $table->string('custom_repeater_item_label')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_groups', function ($table) {
            $table->dropColumn('repeater_item_label')->nullable();
        });

        Schema::table('field_group_instances', function ($table) {
            $table->dropColumn('custom_repeater_item_label')->nullable();
        });
    }
};
