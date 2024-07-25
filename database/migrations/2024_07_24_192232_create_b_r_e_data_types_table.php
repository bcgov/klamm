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

        Schema::create('b_r_e_data_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->foreignId('value_type_id')->constrained();
            $table->text('short_description')->nullable();
            $table->text('long_description')->nullable();
            $table->text('validation')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b_r_e_data_types');
    }
};
