<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot: rule ↔ method (with strategy label and default flag)
        Schema::create('anonymization_rule_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('anonymization_rules')->cascadeOnDelete();
            $table->foreignId('method_id')->constrained('anonymization_methods')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->string('strategy')->nullable()->comment('Null for the default method; a label like "aggressive", "development" for variants');
            $table->timestamps();

            $table->unique(['rule_id', 'method_id']);
            $table->index('strategy');
        });

        // Pivot: column ↔ rule (each column gets one rule)
        Schema::create('anonymization_rule_column', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('anonymization_rules')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('anonymous_siebel_columns')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['column_id']); // A column can only have one rule
            $table->index('rule_id');
        });

        // Add strategy column to jobs so each job can select which strategy to use
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->string('strategy')->nullable()->after('output_format')
                ->comment('Which method strategy to resolve from rules. Null = use defaults.');
        });
    }

    public function down(): void
    {
        Schema::table('anonymization_jobs', function (Blueprint $table) {
            $table->dropColumn('strategy');
        });

        Schema::dropIfExists('anonymization_rule_column');
        Schema::dropIfExists('anonymization_rule_methods');
        Schema::dropIfExists('anonymization_rules');
    }
};
