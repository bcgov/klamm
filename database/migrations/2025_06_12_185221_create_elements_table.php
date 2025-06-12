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
        Schema::create('elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_element_id')->nullable()->constrained('elements')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->integer('order');
            $table->string('custom_label')->nullable();
            $table->boolean('hide_label')->default(false);
            $table->string('custom_data_binding_path')->nullable();
            $table->string('custom_data_binding')->nullable();
            $table->text('custom_help_text')->nullable();
            $table->boolean('visible_web')->default(true);
            $table->boolean('visible_pdf')->default(true);
            $table->string('type'); // 'container' or 'field'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('elements');
    }
};
