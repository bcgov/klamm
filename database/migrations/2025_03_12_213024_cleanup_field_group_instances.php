<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->renameColumn('label', 'custom_group_label');
            $table->renameColumn('customize_label', 'customize_group_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->renameColumn('custom_group_label', 'label');
            $table->renameColumn('customize_group_label', 'customize_label');
        });
    }
};
