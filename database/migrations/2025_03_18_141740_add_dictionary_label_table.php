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
        Schema::create('report_dictionary_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::table('report_entries', function (Blueprint $table) {
            $table->foreignId('report_dictionary_label_id')->nullable()->after('existing_label')
                ->constrained('report_dictionary_labels')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_dictionary_label_id');
        });

        Schema::dropIfExists('report_dictionary_labels');
    }
};
