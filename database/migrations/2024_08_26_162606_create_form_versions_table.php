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
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->onDelete('cascade');
            $table->string('version_number')->nullable();
            $table->enum('status', ['Requested', 'Active', 'Archived', 'In Review', 'Ready to Release'])->default('Requested');
            $table->string('form_requester_name')->nullable();
            $table->string('form_requester_email')->nullable();
            $table->string('form_developer_name')->nullable();
            $table->string('form_developer_email')->nullable();
            $table->string('form_approver_name')->nullable();
            $table->string('form_approver_email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_versions');
    }
};
