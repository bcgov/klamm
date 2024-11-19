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
        Schema::table('form_data_sources', function (Blueprint $table) {
            $table->dropColumn('source');

            $table->string('type')->nullable();
            $table->string('endpoint')->nullable();
            $table->text('params')->nullable();
            $table->text('body')->nullable();
            $table->text('headers')->nullable();
            $table->string('host')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $table->string('source');

        $table->dropColumn('type');
        $table->dropColumn('endpoint');
        $table->dropColumn('params');
        $table->dropColumn('body');
        $table->dropColumn('headers');
        $table->dropColumn('host');
    }
};
