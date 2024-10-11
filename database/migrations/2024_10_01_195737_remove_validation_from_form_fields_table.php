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
            $table->dropColumn('validation');
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->dropColumn('validation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('validation')->nullable();
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('validation')->nullable();
        });
    }
};
