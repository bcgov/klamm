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
        Schema::create('field_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label')->nullable();
            $table->text('help_text')->nullable();
            $table->foreignId('data_type_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('data_binding_path')->nullable();
            $table->string('data_binding')->nullable();
            $table->string('mask')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_templates');
    }
};
