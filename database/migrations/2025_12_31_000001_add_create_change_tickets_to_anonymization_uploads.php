<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_uploads', 'create_change_tickets')) {
                $table->boolean('create_change_tickets')->default(true)->after('import_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_uploads', 'create_change_tickets')) {
                $table->dropColumn('create_change_tickets');
            }
        });
    }
};
