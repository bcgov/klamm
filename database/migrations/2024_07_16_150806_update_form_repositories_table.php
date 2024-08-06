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
        Schema::table('form_repositories', function (Blueprint $table) {
            $table->dropColumn('custodian_id');
            $table->dropColumn('location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_repositories', function (Blueprint $table) {
            $table->foreignId('custodian_id')->nullable()->constrained('contacts');
            $table->text('location')->nullable();
        });
    }
};
