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
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->foreignId('field_group_instance_id')->nullable()->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->dropColumn('field_group_instance_id');
        });
    }
};
