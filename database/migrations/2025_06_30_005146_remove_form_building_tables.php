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
        // Drop tables with foreign keys first
        Schema::dropIfExists('form_instance_field_validations');
        Schema::dropIfExists('form_instance_field_values');
        Schema::dropIfExists('form_instance_field_date_formats');
        Schema::dropIfExists('form_instance_field_conditionals');
        Schema::dropIfExists('select_option_instances');
        Schema::dropIfExists('form_instance_fields');
        
        // Drop field group related tables
        Schema::dropIfExists('field_group_instances');
        Schema::dropIfExists('field_group_form_field');
        
        // Drop form field related tables
        Schema::dropIfExists('form_field_validations');
        Schema::dropIfExists('form_field_values');
        Schema::dropIfExists('form_field_date_formats');
        
        // Drop containers
        Schema::dropIfExists('containers');
        
        // Drop parent tables last
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('field_groups');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not intended to be rolled back
        throw new \Exception('This migration cannot be reversed. Restore from backup if needed.');
    }
};
