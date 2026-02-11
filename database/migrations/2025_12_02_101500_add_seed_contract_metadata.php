<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymization_methods', function (Blueprint $table) {
            $table->boolean('emits_seed')->default(false)->after('sql_block');
            $table->boolean('requires_seed')->default(false)->after('emits_seed');
            $table->boolean('supports_composite_seed')->default(false)->after('requires_seed');
            $table->text('seed_notes')->nullable()->after('supports_composite_seed');
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->string('seed_contract_mode', 32)->nullable()->after('anonymization_required');
            $table->text('seed_contract_expression')->nullable()->after('seed_contract_mode');
            $table->text('seed_contract_notes')->nullable()->after('seed_contract_expression');
        });

        Schema::table('anonymous_siebel_column_dependencies', function (Blueprint $table) {
            $table->string('seed_bundle_label', 120)->nullable()->after('child_field_id');
            $table->json('seed_bundle_components')->nullable()->after('seed_bundle_label');
            $table->boolean('is_seed_mandatory')->default(true)->after('seed_bundle_components');
        });
    }

    public function down(): void
    {
        Schema::table('anonymous_siebel_column_dependencies', function (Blueprint $table) {
            $table->dropColumn(['seed_bundle_label', 'seed_bundle_components', 'is_seed_mandatory']);
        });

        Schema::table('anonymous_siebel_columns', function (Blueprint $table) {
            $table->dropColumn(['seed_contract_mode', 'seed_contract_expression', 'seed_contract_notes']);
        });

        Schema::table('anonymization_methods', function (Blueprint $table) {
            $table->dropColumn(['emits_seed', 'requires_seed', 'supports_composite_seed', 'seed_notes']);
        });
    }
};
