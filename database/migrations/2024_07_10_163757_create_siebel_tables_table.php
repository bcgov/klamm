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
        Schema::disableForeignKeyConstraints();

        Schema::create('siebel_tables', function (Blueprint $table) {
            $table->id();
            $table->string('object_language_locked', 10)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_locked_by_name', 50)->nullable();
            $table->timestamp('object_locked_date')->nullable();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('user_name', 200)->nullable();
            $table->string('alias', 200)->nullable();
            $table->string('type', 50)->nullable();
            $table->boolean('file')->nullable();
            $table->string('abbreviation_1', 50)->nullable();
            $table->string('abbreviation_2', 50)->nullable();
            $table->string('abbreviation_3', 50)->nullable();
            $table->boolean('append_data')->nullable();
            $table->string('dflt_mapping_col_name_prefix', 25)->nullable();
            $table->longText('seed_filter')->nullable();
            $table->longText('seed_locale_filter')->nullable();
            $table->string('seed_usage', 30)->nullable();
            $table->string('group', 25)->nullable();
            $table->string('owner_organization_specifier', 30)->nullable();
            $table->string('status', 25)->nullable();
            $table->boolean('volatile')->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('node_type', 10)->nullable();
            $table->boolean('partition_indicator')->nullable();
            $table->longText('comments')->nullable();
            $table->boolean('external_api_write')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('base_table_id')->nullable()->constrained('siebel_tables');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_tables');
    }
};
