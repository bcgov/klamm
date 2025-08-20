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
        Schema::table('form_interfaces', function (Blueprint $table) {
            $table->string('mode')->nullable()->after('type')->index();
            $table->json('mode_config')->nullable()->after('mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_interfaces', function (Blueprint $table) {
            $table->dropColumn(['mode_config', 'mode']);
        });
    }
};
