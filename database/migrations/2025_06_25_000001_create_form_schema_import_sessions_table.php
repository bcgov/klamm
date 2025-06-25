<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_schema_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_name')->nullable(); // User-friendly name for the import session
            $table->text('description')->nullable(); // Optional description
            $table->enum('status', ['draft', 'in_progress', 'completed', 'failed', 'cancelled'])->default('draft');

            // Schema content and parsing
            $table->longText('schema_content')->nullable(); // The raw JSON content
            $table->json('parsed_schema_summary')->nullable(); // Summary info (form_id, field_count, etc.)
            $table->longText('parsed_schema_data')->nullable(); // Full parsed schema data

            // Import configuration
            $table->string('target_form_id')->nullable();
            $table->string('target_form_title')->nullable();
            $table->foreignId('target_ministry_id')->nullable()->constrained('ministries')->onDelete('set null');
            $table->foreignId('target_form_record_id')->nullable()->constrained('forms')->onDelete('set null');
            $table->boolean('create_new_form')->default(true);
            $table->boolean('create_new_version')->default(true);

            // Field mappings and progress
            $table->json('field_mappings')->nullable(); // Field mapping decisions
            $table->json('import_progress')->nullable(); // Pagination state, current step, etc.
            $table->integer('total_fields')->default(0);
            $table->integer('mapped_fields')->default(0);
            $table->integer('current_step')->default(1); // Wizard step

            // Result tracking
            $table->foreignId('result_form_id')->nullable()->constrained('forms')->onDelete('set null');
            $table->foreignId('result_form_version_id')->nullable()->constrained('form_versions')->onDelete('set null');
            $table->json('import_result')->nullable(); // Success/error messages, stats
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();

            // User and session tracking
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('session_token')->unique(); // For resuming sessions
            $table->timestamp('last_activity_at')->nullable();
            $table->json('browser_session_data')->nullable(); // For storing UI state

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['session_token']);
            $table->index(['status', 'last_activity_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_schema_import_sessions');
    }
};
