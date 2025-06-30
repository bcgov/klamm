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
        Schema::table('form_versions', function (Blueprint $table) {
            $table->dropColumn(['deployed_to', 'deployed_at']);
        });
        
        Schema::dropIfExists('select_options');
        Schema::dropIfExists('data_types');
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
