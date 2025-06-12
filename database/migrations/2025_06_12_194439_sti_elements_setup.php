<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('element_date_formats');
        Schema::dropIfExists('element_values');
        Schema::dropIfExists('element_validations');
        Schema::dropIfExists('element_conditionals');
        Schema::dropIfExists('select_option_instances');
        Schema::dropIfExists('containers');
        Schema::dropIfExists('fields');
        Schema::dropIfExists('elements');

        Schema::create('elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_element_id')->nullable()->references('id')->on('elements')->onDelete('cascade');
            $table->uuid('uuid')->unique();
            $table->string('type'); // 'field' or 'container'
            $table->integer('order');
            $table->string('custom_label')->nullable();
            $table->boolean('hide_label')->default(false);
            $table->string('custom_data_binding_path')->nullable();
            $table->string('custom_data_binding')->nullable();
            $table->string('custom_help_text')->nullable();
            $table->boolean('visible_web')->default(true);
            $table->boolean('visible_pdf')->default(true);

            // Container-specific fields
            $table->boolean('has_repeater')->nullable();
            $table->boolean('has_clear_button')->nullable();
            $table->string('repeater_item_label')->nullable();

            // Field-specific fields
            $table->foreignId('field_template_id')->nullable()->constrained();
            $table->string('custom_mask')->nullable();

            $table->timestamps();

            // Add indexes for better performance
            $table->index('type');
            $table->index('form_version_id');
            $table->index('parent_element_id');
            $table->index('field_template_id');
        });

        Schema::create('element_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->text('value')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('element_conditionals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('element_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('element_date_formats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->onDelete('cascade');
            $table->string('format')->nullable();
            $table->timestamps();
        });

        Schema::create('select_option_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('element_id')->constrained()->onDelete('cascade');
            $table->string('value');
            $table->string('text');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }


    public function down()
    {
        Schema::dropIfExists('select_option_instances');
        Schema::dropIfExists('element_date_formats');
        Schema::dropIfExists('element_values');
        Schema::dropIfExists('element_conditionals');
        Schema::dropIfExists('element_validations');
        Schema::dropIfExists('elements');
    }
};
