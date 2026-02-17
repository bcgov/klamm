<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_uploads', 'override_anonymization_rules')) {
                $table->boolean('override_anonymization_rules')->default(false)->after('create_change_tickets');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_uploads', 'override_anonymization_rules')) {
                $table->dropColumn('override_anonymization_rules');
            }
        });
    }
};
