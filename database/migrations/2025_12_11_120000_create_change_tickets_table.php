<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('change_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('open'); // open, in_progress, resolved, dismissed
            $table->string('priority')->default('normal'); // low, normal, high
            $table->string('scope_type')->nullable(); // database|schema|table|column|upload
            $table->string('scope_name')->nullable();
            $table->text('impact_summary')->nullable();
            $table->longText('diff_payload')->nullable(); // JSON of differences
            $table->foreignId('upload_id')->nullable()->constrained('anonymization_uploads')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index(['scope_type', 'scope_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_tickets');
    }
};
