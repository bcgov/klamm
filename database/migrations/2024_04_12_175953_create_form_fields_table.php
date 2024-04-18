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

        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('label', 400)->nullable();
            $table->text('help_text')->nullable();
            $table->foreignId('data_type_id')->constrained();
            $table->text('description')->nullable();
            $table->foreignId('field_group_id')->nullable()->constrained();
            $table->text('validation')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('repeater')->default(false);
            $table->string('max_count')->nullable();
            $table->text('conditional_logic')->nullable();
            $table->boolean('prepopulated')->default(false);
            $table->foreignId('datasource_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
