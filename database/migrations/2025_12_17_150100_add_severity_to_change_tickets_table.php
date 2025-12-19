<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('change_tickets', 'severity')) {
                $table->string('severity')->default('low')->after('priority');
                $table->index(['status', 'severity']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('change_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('change_tickets', 'severity')) {
                $table->dropIndex(['status', 'severity']);
                $table->dropColumn('severity');
            }
        });
    }
};
