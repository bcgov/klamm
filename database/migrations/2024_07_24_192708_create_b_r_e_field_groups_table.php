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

        Schema::create('b_r_e_field_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('label', 400)->nullable();
            $table->text('description')->nullable();
            $table->text('internal_description')->nullable();
            $table->timestamps();
        });

        Schema::create('b_r_e_field_b_r_e_field_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('b_r_e_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('b_r_e_field_group_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_r_e_field_b_r_e_field_group');
        Schema::dropIfExists('b_r_e_field_groups');
    }
};
