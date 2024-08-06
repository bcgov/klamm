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

        Schema::create('siebel_business_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->boolean('cache_data')->nullable();
            $table->string('data_source', 50)->nullable();
            $table->boolean('dirty_reads')->nullable();
            $table->boolean('distinct')->nullable();
            $table->string('enclosure_id_field', 50)->nullable();
            $table->boolean('force_active')->nullable();
            $table->boolean('gen_reassign_act')->nullable();
            $table->string('hierarchy_parent_field', 30)->nullable();
            $table->enum('type', ["Transient","Non-Transient"]);
            $table->boolean('inactive')->nullable();
            $table->boolean('insert_update_all_columns')->nullable();
            $table->boolean('log_changes')->nullable();
            $table->integer('maximum_cursor_size')->nullable();
            $table->boolean('multirecipient_select')->nullable();
            $table->boolean('no_delete')->nullable();
            $table->boolean('no_insert')->nullable();
            $table->boolean('no_update')->nullable();
            $table->boolean('no_merge')->nullable();
            $table->boolean('owner_delete')->nullable();
            $table->boolean('placeholder')->nullable();
            $table->boolean('popup_visibility_auto_all')->nullable();
            $table->string('popup_visibility_type', 30)->nullable();
            $table->integer('prefetch_size')->nullable();
            $table->string('recipient_id_field', 30)->nullable();
            $table->integer('reverse_fill_threshold')->nullable();
            $table->boolean('scripted')->nullable();
            $table->longText('search_specification')->nullable();
            $table->longText('sort_specification')->nullable();
            $table->string('status_field', 100)->nullable();
            $table->string('synonym_field', 100)->nullable();
            $table->string('upgrade_ancestor', 200)->nullable();
            $table->string('xa_attribute_value_bus_comp', 100)->nullable();
            $table->string('xa_class_id_field', 100)->nullable();
            $table->string('comments', 500)->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('class_id')->nullable()->constrained('siebel_classes');
            $table->foreignId('table_id')->nullable()->constrained('siebel_tables');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_business_components');
    }
};
