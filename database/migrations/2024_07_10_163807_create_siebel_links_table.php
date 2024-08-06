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

        Schema::create('siebel_links', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('source_field', 100)->nullable();
            $table->string('destination_field', 100)->nullable();
            $table->string('inter_parent_column', 100)->nullable();
            $table->string('inter_child_column', 100)->nullable();
            $table->boolean('inter_child_delete')->nullable();
            $table->string('primary_id_field', 100)->nullable();
            $table->string('cascade_delete', 50);
            $table->string('search_specification', 500)->nullable();
            $table->string('association_list_sort_specification', 100)->nullable();
            $table->boolean('no_associate')->nullable();
            $table->boolean('no_delete')->nullable();
            $table->boolean('no_insert')->nullable();
            $table->boolean('no_inter_delete')->nullable();
            $table->boolean('no_update')->nullable();
            $table->boolean('visibility_auto_all')->nullable();
            $table->string('visibility_rule_applied', 50)->nullable();
            $table->string('visibility_type', 50)->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 400)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 50)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('parent_business_component_id')->nullable()->constrained('siebel_business_components');
            $table->foreignId('child_business_component_id')->nullable()->constrained('siebel_business_components');
            $table->foreignId('inter_table_id')->nullable()->constrained('siebel_tables');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_links');
    }
};
