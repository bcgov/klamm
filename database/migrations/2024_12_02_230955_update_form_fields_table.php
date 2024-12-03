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
            $table->string('mask')->nullable();
        });

        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('mask')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('mask');
        });

        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->dropColumn('mask');
        });
    }
};
