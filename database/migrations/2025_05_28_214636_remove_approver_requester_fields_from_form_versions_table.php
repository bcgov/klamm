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
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn([
                'form_requester_name',
                'form_requester_email',
                'form_approver_name',
                'form_approver_email'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_versions', function (Blueprint $table) {
            $table->string('form_requester_name')->nullable();
            $table->string('form_requester_email')->nullable();
            $table->string('form_approver_name')->nullable();
            $table->string('form_approver_email')->nullable();
        });
    }
};
