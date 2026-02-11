<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymous_siebel_tables', function (Blueprint $table) {
            $table->string('target_relation_kind', 16)
                ->nullable()
                ->index()
                ->after('object_type');
        });
    }

    public function down(): void
    {
        Schema::table('anonymous_siebel_tables', function (Blueprint $table) {
            $table->dropIndex(['target_relation_kind']);
            $table->dropColumn('target_relation_kind');
        });
    }
};
