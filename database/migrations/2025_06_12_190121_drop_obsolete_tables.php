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
        Schema::dropIfExists('form_field_validations');
        Schema::dropIfExists('form_field_values');
        Schema::dropIfExists('form_field_date_formats');
        Schema::dropIfExists('select_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('field_groups');
        Schema::dropIfExists('field_group_instances');
        Schema::dropIfExists('form_instance_fields');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // We don't need to recreate these tables as they are being replaced
        // with a new schema
    }
};
