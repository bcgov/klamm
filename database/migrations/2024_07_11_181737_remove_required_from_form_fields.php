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
        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn('validation');
            $table->dropColumn('required');
            $table->dropColumn('repeater');
            $table->dropColumn('max_count');
            $table->dropColumn('conditional_logic');
            $table->dropColumn('prepopulated');
            $table->dropForeign(['datasource_id']);
            $table->dropColumn('datasource_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table) {
            $table->text('validation')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('repeater')->default(false);
            $table->string('max_count')->nullable();
            $table->text('conditional_logic')->nullable();
            $table->boolean('prepopulated')->default(false);
            $table->foreignId('datasource_id')->nullable()->constrained('datasources');
        });
    }
};
