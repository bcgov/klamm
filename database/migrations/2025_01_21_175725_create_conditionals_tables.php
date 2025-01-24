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
        Schema::create('form_instance_field_conditionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_instance_field_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->string('value')->nullable();
            $table->timestamps();
        });
        Schema::table('field_group_instances', function ($table) {
            $table->string('visibility')->nullable();
        });
        Schema::table('form_fields', function ($table) {
            $table->dropColumn('conditional_logic');
        });
        Schema::table('form_instance_fields', function ($table) {
            $table->dropColumn('conditional_logic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_instance_field_conditionals');
        Schema::table('field_group_instances', function ($table) {
            $table->dropColumn('visibility');
        });
        Schema::table('form_fields', function (Blueprint $table) {
            $table->string('conditional_logic')->nullable();
        });
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('conditional_logic')->nullable();
        });
    }
};
