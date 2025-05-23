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
        Schema::create('error_entities', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create('error_data_groups', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create('error_integration_states', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create('error_actors', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create('error_sources', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->timestamps();
        });

        Schema::create('system_messages', function (Blueprint $table) {
            $table->id();
            $table->string("error_code");
            $table->string("error_message");
            $table->string("icm_error_solution")->nullable();
            $table->string("explanation")->nullable();
            $table->string("fix")->nullable();
            $table->foreignId('error_entity_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('error_data_group_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('error_integration_state_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('error_actor_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('error_source_id')->nullable()->constrained()->onDelete('cascade');
            $table->boolean("service_desk")->nullable();
            $table->boolean("limited_data")->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_messages');
        Schema::dropIfExists('error_entities');
        Schema::dropIfExists('error_data_groups');
        Schema::dropIfExists('error_integration_states');
        Schema::dropIfExists('error_actors');
        Schema::dropIfExists('error_sources');
    }
};
