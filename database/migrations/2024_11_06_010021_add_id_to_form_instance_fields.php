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
        Schema::table('form_instance_fields', function (Blueprint $table) {
            $table->string('custom_id')->nullable();
            $table->unique(['form_version_id', 'custom_id','field_group_instance_id'], 'form_version_custom_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_instance_fields', function (Blueprint $table) {            
            $table->dropUnique('form_version_custom_id_unique');
            $table->dropColumn('custom_id');
        });
    }
};
