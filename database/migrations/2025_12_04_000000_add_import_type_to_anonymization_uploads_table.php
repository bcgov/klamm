<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            $table->string('import_type', 20)->default('partial')->after('original_name');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_uploads', function (Blueprint $table) {
            $table->dropColumn('import_type');
        });
    }
};
