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
        Schema::create('form_elements', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('name')->nullable();
            $table->foreignId('form_versions_id')->constrained('form_versions')->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('form_elements')->onDelete('cascade');
            $table->unsignedBigInteger('elementable_id');
            $table->string('elementable_type');
            $table->text('help_text')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_repeatable')->default(false);
            $table->string('repeater_item_label')->nullable();
            $table->boolean('is_resetable')->default(false);
            $table->boolean('visible_web')->default(true);
            $table->boolean('visible_pdf')->default(true);
            $table->boolean('is_template')->default(false);
            $table->foreignId('form_field_data_bindings_id')->nullable()->constrained('form_field_data_bindings')->onDelete('set null');
            $table->integer('order')->default(0); // For ordering within parent
            $table->timestamps();

            // Index for polymorphic relationship
            $table->index(['elementable_id', 'elementable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_elements');
    }
};
