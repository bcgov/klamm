<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (! Schema::hasColumn('anonymization_uploads', 'scope_type')) {
                $table->string('scope_type')->nullable()->after('original_name');
            }
            if (! Schema::hasColumn('anonymization_uploads', 'scope_name')) {
                $table->string('scope_name')->nullable()->after('scope_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            if (Schema::hasColumn('anonymization_uploads', 'scope_name')) {
                $table->dropColumn('scope_name');
            }
            if (Schema::hasColumn('anonymization_uploads', 'scope_type')) {
                $table->dropColumn('scope_type');
            }
        });
    }
};
