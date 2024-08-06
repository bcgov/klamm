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

        Schema::create('siebel_eim_interface_tables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->boolean('changed');
            $table->string('user_name', 400);
            $table->string('type', 30);
            $table->boolean('file');
            $table->string('eim_delete_proc_column', 30)->nullable();
            $table->string('eim_export_proc_column', 30)->nullable();
            $table->string('eim_merge_proc_column', 30)->nullable();
            $table->boolean('inactive');
            $table->longText('comments')->nullable();
            $table->boolean('object_locked')->nullable();
            $table->string('object_language_locked', 10)->nullable();
            $table->foreignId('project_id')->nullable()->constrained('siebel_projects');
            $table->foreignId('target_table_id')->nullable()->constrained('siebel_tables');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siebel_eim_interface_tables');
    }
};
