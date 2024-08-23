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

        Schema::create('bre_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 400);
            $table->string('label', 400)->nullable();
            $table->text('description')->nullable();
            $table->text('internal_description')->nullable();
            $table->timestamps();
        });

        Schema::create('bre_field_bre_rule_input', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('bre_rule_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('bre_field_bre_rule_output', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bre_field_id')->constrained()->onDelete('cascade');
            $table->foreignId('bre_rule_id')->constrained()->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('bre_rule_bre_rule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_rule_id')->nullable()->constrained('bre_rules')->onDelete('cascade');
            $table->foreignId('child_rule_id')->constrained('bre_rules')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bre_rule_bre_rule');
        Schema::dropIfExists('bre_field_bre_rule_output');
        Schema::dropIfExists('bre_field_bre_rule_input');
        Schema::dropIfExists('bre_rules');
    }
};
