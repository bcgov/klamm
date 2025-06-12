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
        Schema::dropIfExists('form_instance_field_validations');
        Schema::dropIfExists('form_instance_field_conditionals');
        Schema::dropIfExists('form_instance_field_values');
        Schema::dropIfExists('form_instance_field_date_formats');
        Schema::dropIfExists('select_option_instances');
        Schema::dropIfExists('style_instances');
        Schema::dropIfExists('form_instance_fields');
        Schema::dropIfExists('field_group_instances');
        Schema::dropIfExists('containers');
        Schema::dropIfExists('field_group_form_field');
        Schema::dropIfExists('form_field_style_web');
        Schema::dropIfExists('form_field_style_pdf');
        Schema::dropIfExists('field_group_style_web');
        Schema::dropIfExists('field_group_style_pdf');
        Schema::dropIfExists('form_field_validations');
        Schema::dropIfExists('form_field_values');
        Schema::dropIfExists('form_field_date_formats');
        Schema::dropIfExists('select_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('field_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
