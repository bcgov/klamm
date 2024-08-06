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

        Schema::create('siebel_views', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('repository_name', 400);
            $table->string('visibility_applet', 250)->nullable();
            $table->string('visibility_applet_type', 200)->nullable();
            $table->boolean('admin_mode_flag')->nullable();
            $table->string('thread_applet', 400)->nullable();
            $table->string('thread_field', 250)->nullable();
            $table->string('thread_title', 250)->nullable();
            $table->string('thread_title_string_reference', 400)->nullable();
            $table->string('thread_title_string_override', 250)->nullable();
            $table->boolean('inactive')->nullable();
            $table->string('comments', 500)->nullable();
            $table->string('bitmap_category', 250)->nullable();
            $table->string('drop_sectors', 30)->nullable();
            $table->boolean('explicit_login')->nullable();
            $table->string('help_identifier', 200)->nullable();
            $table->boolean('no_borders')->nullable();
            $table->boolean('screen_menu')->nullable();
            $table->string('sector0_applet', 200)->nullable();
            $table->string('sector1_applet', 200)->nullable();
            $table->string('sector2_applet', 200)->nullable();
            $table->string('sector3_applet', 200)->nullable();
            $table->string('sector4_applet', 200)->nullable();
            $table->string('sector5_applet', 200)->nullable();
            $table->string('sector6_applet', 200)->nullable();
            $table->string('sector7_applet', 200)->nullable();
            $table->boolean('secure')->nullable();
            $table->string('status_text', 200)->nullable();
            $table->string('status_text_string_reference', 200)->nullable();
            $table->string('status_text_string_override', 200)->nullable();
            $table->string('title', 200)->nullable();
            $table->string('title_string_reference', 200)->nullable();
            $table->string('title_string_override', 200)->nullable();
            $table->integer('vertical_line_position')->nullable();
            $table->string('upgrade_behavior', 30)->nullable();
            $table->string('icl_upgrade_path', 200)->nullable();
            $table->boolean('add_to_history')->nullable();
            $table->string('task', 200)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('default_applet_focus', 200)->nullable();
            $table->boolean('disable_pdq')->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 20)->nullable();
            $table->foreignId('business_object_id')->nullable()->constrained('siebel_business_objects');
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_views');
    }
};
