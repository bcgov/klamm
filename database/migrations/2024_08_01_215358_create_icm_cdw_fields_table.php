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

        Schema::create('icm_cdw_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('field')->nullable();
            $table->text('panel_type')->nullable();
            $table->text('entity')->nullable();
            $table->text('path')->nullable();
            $table->text('subject_area')->nullable();
            $table->text('applet')->nullable();
            $table->text('datatype')->nullable();
            $table->string('field_input_max_length', 400)->nullable();
            $table->string('ministry', 400)->nullable();
            $table->text('cdw_ui_caption')->nullable();
            $table->string('cdw_table_name', 400)->nullable();
            $table->string('cdw_column_name', 400)->nullable();
            $table->timestamps();
        });

        Schema::create('bre_field_icm_cdw_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('icm_cdw_field_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bre_field_icm_cdw_field');
        Schema::dropIfExists('icm_cdw_fields');
    }
};
