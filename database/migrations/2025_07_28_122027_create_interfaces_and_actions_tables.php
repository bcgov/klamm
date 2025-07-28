<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_interfaces', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->string('style')->nullable();
            $table->text('condition')->nullable();
            $table->timestamps();
        });

        Schema::create('interface_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_interface_id')->constrained('form_interfaces')->onDelete('cascade');
            $table->string('label');
            $table->string('action_type')->nullable();
            $table->string('type')->nullable();
            $table->string('host')->nullable();
            $table->string('path')->nullable();
            $table->string('authentication')->nullable();
            $table->json('headers')->nullable();
            $table->json('body')->nullable();
            $table->json('parameters')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        Schema::create('form_version_form_interfaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained('form_versions')->onDelete('cascade');
            $table->foreignId('form_interface_id')->constrained('form_interfaces')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_version_form_interfaces');
        Schema::dropIfExists('interface_actions');
        Schema::dropIfExists('form_interfaces');
    }
};
