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
        Schema::table('form_elements', function (Blueprint $table) {
            $table->text('help_text')->nullable();
            $table->string('calculated_value')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_read_only')->default(false);
            $table->boolean('save_on_submit')->default(true);
            $table->boolean('visible_web')->default(true);
            $table->boolean('visible_pdf')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_elements', function (Blueprint $table) {
            $table->dropColumn([
                'help_text',
                'calculated_value',
                'is_visible',
                'is_read_only',
                'save_on_submit',
                'visible_web',
                'visible_pdf',
            ]);
        });
    }
};
