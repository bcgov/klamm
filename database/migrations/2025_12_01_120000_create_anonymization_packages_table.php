<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('handle')->unique();
            $table->string('package_name')->nullable();
            $table->string('database_platform')->default('oracle');
            $table->text('summary')->nullable();
            $table->longText('install_sql')->nullable();
            $table->longText('package_spec_sql')->nullable();
            $table->longText('package_body_sql')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymization_packages');
    }
};
