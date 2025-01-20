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
        Schema::table('field_groups', function (Blueprint $table) {
            $table->text('data_binding')->nullable();
            $table->string('data_binding_path')->nullable();
        });

        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->text('custom_data_binding')->nullable();
            $table->string('custom_data_binding_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_groups', function (Blueprint $table) {
            $table->dropColumn('data_binding');
            $table->dropColumn('data_binding_path');
        });

        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->dropColumn('custom_data_binding');
            $table->dropColumn('custom_data_binding_path');
        });
    }
};
