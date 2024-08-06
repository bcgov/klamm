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

        Schema::create('momus_r_s', function (Blueprint $table) {
            $table->id();
            $table->string('field_name', 30);
            $table->string('description', 300)->nullable();
            $table->string('field_type', 30);
            $table->integer('field_type_length');
            $table->string('source', 30);
            $table->string('screen', 30)->nullable();
            $table->string('table', 30)->nullable();
            $table->string('condition', 100)->nullable();
            $table->string('table_code', 10)->nullable();
            $table->string('lookup_field', 30)->nullable();
            $table->string('database_name', 30)->nullable();
            $table->foreignId('integration_id')->nullable()->constrained();
            $table->foreignId('xml_id')->nullable()->constrained();
            $table->foreignId('lookup_id')->nullable()->constrained();
            $table->boolean('have_duplicate')->default(false);
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('momus_r_s');
    }
};
