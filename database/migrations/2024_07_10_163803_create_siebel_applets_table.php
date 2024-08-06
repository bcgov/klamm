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

        Schema::create('siebel_applets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('title', 250)->nullable();
            $table->string('title_string_reference', 250)->nullable();
            $table->string('title_string_override', 250)->nullable();
            $table->string('search_specification', 400)->nullable();
            $table->string('associate_applet', 250)->nullable();
            $table->string('type', 25)->nullable();
            $table->boolean('no_delete')->nullable();
            $table->boolean('no_insert')->nullable();
            $table->boolean('no_merge')->nullable();
            $table->boolean('no_update')->nullable();
            $table->integer('html_number_of_rows')->nullable();
            $table->boolean('scripted')->nullable();
            $table->boolean('inactive')->nullable();
            $table->longText('comments')->nullable();
            $table->longText('auto_query_mode')->nullable();
            $table->string('background_bitmap_style', 50)->nullable();
            $table->string('html_popup_dimension', 50)->nullable();
            $table->integer('height')->nullable();
            $table->string('help_identifier', 150)->nullable();
            $table->string('insert_position', 50)->nullable();
            $table->string('mail_address_field', 50)->nullable();
            $table->string('mail_template', 50)->nullable();
            $table->string('popup_dimension', 50)->nullable();
            $table->string('upgrade_ancestor', 50)->nullable();
            $table->integer('width')->nullable();
            $table->string('upgrade_behavior', 25)->nullable();
            $table->integer('icl_upgrade_path')->nullable();
            $table->string('task', 50)->nullable();
            $table->string('default_applet_method', 50)->nullable();
            $table->string('default_double_click_method', 50)->nullable();
            $table->boolean('disable_dataloss_warning')->nullable();
            $table->boolean('object_locked')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('business_component_id')->nullable()->constrained('siebel_business_components');
            $table->foreignId('class_id')->nullable()->constrained('siebel_classes');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_applets');
    }
};
