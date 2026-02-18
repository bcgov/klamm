<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_method_column', function (Blueprint $table) {
            $table->index(['column_id'], 'anon_method_column_column_id_idx');
            $table->index(['column_id', 'method_id'], 'anon_method_column_column_method_idx');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_method_column', function (Blueprint $table) {
            $table->dropIndex('anon_method_column_column_method_idx');
            $table->dropIndex('anon_method_column_column_id_idx');
        });
    }
};
