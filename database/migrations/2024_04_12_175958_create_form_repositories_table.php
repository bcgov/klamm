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

        Schema::create('form_repositories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->text('description')->nullable();
            $table->text('location')->nullable();
            $table->foreignId('custodian_id')->nullable()->constrained('contacts');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_repositories');
    }
};
