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
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->integer('order');
            $table->string('instance_id');
            $table->string('custom_instance_id')->nullable();
            $table->string('visibility')->nullable();
            $table->timestamps();
        });

        Schema::table('style_instances', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->constrained()->onDelete('cascade');
        });

        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->constrained()->onDelete('cascade');
        });

        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('style_instances', function (Blueprint $table) {
            $table->dropColumn('container_id');
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->dropColumn('container_id');
        });
        Schema::table('field_group_instances', function (Blueprint $table) {
            $table->dropColumn('container_id');
        });
        Schema::dropIfExists('containers');
    }
};
