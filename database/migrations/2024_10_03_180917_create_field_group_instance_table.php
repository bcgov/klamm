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
        Schema::create('field_group_instances', function (Blueprint $table) {
            $table->foreignId('form_version_id')->constrained()->onDelete('cascade');
            $table->foreignId('field_group_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('label')->nullable();
            $table->boolean('repeater')->default(false);
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_group_instances');
    }
};
