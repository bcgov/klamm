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
        Schema::disableForeignKeyConstraints();

        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->string('lookup_field', 30);
            $table->string('lookup_table', 30);
            $table->string('lookup_table_code', 10);
            $table->string('lookup_database', 30);
            $table->string('description', 300);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
