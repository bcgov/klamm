<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE form_fields ALTER COLUMN label TYPE TEXT');
        DB::statement('ALTER TABLE form_instance_fields ALTER COLUMN custom_label TYPE TEXT');
        DB::statement('ALTER TABLE form_instance_field_conditionals ALTER COLUMN value TYPE TEXT');
        DB::statement('ALTER TABLE form_instance_field_validations ALTER COLUMN value TYPE TEXT');
        DB::statement('ALTER TABLE field_group_instances ALTER COLUMN visibility TYPE TEXT');
        DB::statement('ALTER TABLE containers ALTER COLUMN visibility TYPE TEXT');
    }

    /**
     * Reverse the migrations.
     * * * WARNING: May lead to data loss if content of column is greater than 255 chars! * * *
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE form_fields ALTER COLUMN label TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE form_instance_fields ALTER COLUMN custom_label TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE form_instance_field_conditionals ALTER COLUMN value TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE form_instance_field_validations ALTER COLUMN value TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE field_group_instances ALTER COLUMN visibility TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE containers ALTER COLUMN visibility TYPE VARCHAR(255)');
    }
};
