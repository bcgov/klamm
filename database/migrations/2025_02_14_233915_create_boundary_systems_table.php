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
        Schema::create('boundary_systems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ministry_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('interface_name')->nullable();
            $table->boolean('active')->nullable()->default(false);
            $table->text('comments')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boundary_systems');
    }
};
