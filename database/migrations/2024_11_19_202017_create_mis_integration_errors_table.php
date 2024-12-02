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
        Schema::create('mis_integration_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('data_group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('view')->nullable();
            $table->text('message_copy')->nullable();
            $table->text('fix')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mis_integration_errors');
    }
};
