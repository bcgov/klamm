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
        Schema::create('form_instance_fields', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->default(0);
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('form_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('field_group_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('label')->nullable();
            $table->string('data_binding')->nullable();
            $table->string('validation')->nullable();
            $table->string('conditional_logic')->nullable();
            $table->string('styles')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_instance_fields');
    }
};
