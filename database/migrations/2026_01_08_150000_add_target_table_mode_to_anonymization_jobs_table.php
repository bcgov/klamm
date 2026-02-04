<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->string('target_table_mode', 32)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropIndex(['target_table_mode']);
            $table->dropColumn('target_table_mode');
        });
    }
};
