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
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->boolean('clear_button')->default(false);
        });

        Schema::table('field_groups', function (Blueprint $table) {
            $table->boolean('clear_button')->default(false);
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->boolean('clear_button')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->dropColumn('clear_button');
        });

        Schema::table('field_groups', function (Blueprint $table) {
            $table->dropColumn('clear_button');
        });

        Schema::table('containers', function (Blueprint $table) {
            $table->dropColumn('clear_button');
        });
    }
};
