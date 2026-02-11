<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            // For persistent seed stores, Oracle recommends dropping mapping tables before distributing masked datasets.
            // We keep this as a script-generation option (commented vs executable) so teams can decide at execution time.
            $table->string('seed_map_hygiene_mode')->default('commented')->after('seed_store_prefix');
            $table->index('seed_map_hygiene_mode');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropIndex(['seed_map_hygiene_mode']);
            $table->dropColumn(['seed_map_hygiene_mode']);
        });
    }
};
