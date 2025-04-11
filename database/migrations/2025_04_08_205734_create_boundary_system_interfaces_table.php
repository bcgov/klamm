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
        Schema::create('boundary_system_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('boundary_system_contact_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('boundary_system_contacts')->onDelete('cascade');
            $table->string('email');
            $table->timestamps();
        });


        Schema::create('boundary_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_external')->default(false);
            $table->foreignId('contact_id')->nullable()->constrained('boundary_system_contacts')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('boundary_system_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('boundary_system_interfaces', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_description');
            $table->text('description');
            $table->foreignId('source_system_id')->constrained('boundary_systems')->onDelete('cascade');
            $table->foreignId('target_system_id')->constrained('boundary_systems')->onDelete('cascade');
            $table->enum('transaction_frequency', ['on_demand', 'hourly', 'multiple_times_a_day', 'daily', 'weekly', 'monthly', 'quarterly', 'annually', 'custom'])->nullable();
            $table->string('transaction_schedule')->nullable();
            $table->enum('complexity', ['high', 'medium', 'low'])->default('high');
            $table->enum('integration_type', ['api_soap', 'api_rest', 'file_transfer', 'database_integration', 'messaging_queue', 'event_driven', 'etl', 'unknown']);
            $table->enum('mode_of_transfer', ['batch', 'asynchronous', 'synchronous', 'real_time', 'near_real_time', 'unknown']);
            $table->enum('protocol', ['soap', 'http', 'ftp', 'sftp', 'jdbc', 'odbc', 'mq_series', 'jms', 'webservice', 'rest', 'ssh', 'unknown']);
            $table->json('data_format');
            $table->json('security')->nullable();
            $table->timestamps();
        });

        Schema::create('boundary_system_interface_tag', function (Blueprint $table) {
            $table->foreignId('boundary_system_interface_id')->constrained()->onDelete('cascade');
            $table->foreignId('boundary_system_tag_id')->constrained()->onDelete('cascade');
            $table->primary(['boundary_system_interface_id', 'boundary_system_tag_id'], 'bsi_tag_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_system_interface_tag');
        Schema::dropIfExists('boundary_system_interfaces');
        Schema::dropIfExists('boundary_system_tags');
        Schema::dropIfExists('boundary_systems');
        Schema::dropIfExists('boundary_system_contact_emails');
        Schema::dropIfExists('boundary_system_contacts');
    }
};
