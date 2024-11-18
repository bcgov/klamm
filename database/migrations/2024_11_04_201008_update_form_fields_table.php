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
        Schema::table('form_fields', function (Blueprint $table) {
            $table->text('data_binding')->nullable()->change();
            $table->string('data_binding_path')->nullable();
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->text('data_binding')->nullable()->change();
            $table->string('data_binding_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('data_binding')->nullable()->change();
            $table->dropColumn('data_binding_path');
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('data_binding')->nullable()->change();
            $table->dropColumn('data_binding_path');
        });
    }
};
