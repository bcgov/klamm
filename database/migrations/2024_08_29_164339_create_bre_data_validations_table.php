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

        Schema::table('bre_fields', function (Blueprint $table) {
            $table->dropForeign(['data_type_id']);
            $table->foreign('data_type_id')->references('id')->on('bre_data_types')->change();
        });

        Schema::create('bre_validation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->string('value', 400)->nullable();
            $table->timestamps();
        });

        Schema::create('bre_data_validations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->foreignId('validation_type_id')->nullable()->constrained('bre_validation_types');
            $table->text('validation_criteria', 400)->nullable();
            $table->timestamps();
        });


        Schema::table('bre_fields', function (Blueprint $table) {
            $table->foreignId('data_validation_id')->nullable()->constrained('bre_data_validations')->after('data_type_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('bre_fields', function (Blueprint $table) {
            $table->dropForeign(['data_validation_id']);
            $table->dropColumn('data_validation_id');
        });

        Schema::dropIfExists('bre_data_validations');
        Schema::dropIfExists('bre_validation_types');

        Schema::table('bre_fields', function (Blueprint $table) {
            $table->dropForeign(['data_type_id']);
            $table->foreign('data_type_id')->references('id')->on('data_types')->change();
        });

        Schema::enableForeignKeyConstraints();
    }
};
