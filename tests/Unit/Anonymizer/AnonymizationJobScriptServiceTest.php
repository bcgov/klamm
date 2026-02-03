<?php

use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelDataType;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Minimal catalog graph.
    $db = AnonymousSiebelDatabase::query()->create([
        'database_name' => 'SBLDEV',
        'content_hash' => md5('SBLDEV'),
    ]);
    $schema = AnonymousSiebelSchema::query()->create([
        'database_id' => $db->id,
        'schema_name' => 'SIEBEL',
        'content_hash' => md5('SIEBEL'),
    ]);
    $table = AnonymousSiebelTable::query()->create([
        'schema_id' => $schema->id,
        'table_name' => 'S_CONTACT',
        'object_type' => 'table',
        'content_hash' => md5('S_CONTACT'),
    ]);

    $varchar = AnonymousSiebelDataType::query()->create([
        'data_type_name' => 'Varchar',
        'description' => '',
        'content_hash' => md5('Varchar'),
    ]);

    $provider = AnonymousSiebelColumn::query()->create([
        'table_id' => $table->id,
        'data_type_id' => $varchar->id,
        'column_name' => 'INTEGRATION_ID',
        'data_length' => 30,
        'nullable' => false,
        'content_hash' => md5('INTEGRATION_ID'),
        'seed_contract_expression' => "STANDARD_HASH(tgt.INTEGRATION_ID || {{JOB_SEED_LITERAL}}, 'SHA256')",
    ]);

    $consumer = AnonymousSiebelColumn::query()->create([
        'table_id' => $table->id,
        'data_type_id' => $varchar->id,
        'column_name' => 'PAR_ROW_ID',
        'data_length' => 30,
        'nullable' => true,
        'content_hash' => md5('PAR_ROW_ID'),
    ]);

    // Wire dependency: consumer depends on provider.
    DB::table('anonymous_siebel_column_dependencies')->insert([
        'parent_field_id' => $provider->id,
        'child_field_id' => $consumer->id,
        'seed_bundle_label' => null,
        'seed_bundle_components' => null,
        'is_seed_mandatory' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Methods.
    $providerMethod = AnonymizationMethods::query()->create([
        'name' => 'Emit deterministic seed',
        'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
        'sql_block' => "UPDATE {{TABLE}} tgt SET {{COLUMN}} = {{SEED_EXPR}};",
        'emits_seed' => true,
        'requires_seed' => false,
        'supports_composite_seed' => false,
    ]);

    $consumerMethod = AnonymizationMethods::query()->create([
        'name' => 'Reuse deterministic seed',
        'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
        'sql_block' => "UPDATE {{TABLE}} tgt SET {{COLUMN}} = {{SEED_MAP_LOOKUP}};",
        'emits_seed' => false,
        'requires_seed' => true,
        'supports_composite_seed' => false,
    ]);

    // Attach methods.
    DB::table('anonymization_method_column')->insert([
        ['method_id' => $providerMethod->id, 'column_id' => $provider->id, 'created_at' => now(), 'updated_at' => now()],
        ['method_id' => $consumerMethod->id, 'column_id' => $consumer->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Make sure relationships exist for the service.
    $provider->load(['table.schema.database', 'dataType', 'parentColumns']);
    $consumer->load(['table.schema.database', 'dataType', 'parentColumns']);

    $this->providerId = $provider->id;
    $this->consumerId = $consumer->id;
});

test('persistent seed maps use create-if-missing and merge, and honor job seed literal', function () {
    $job = AnonymizationJobs::query()->create([
        'name' => 'Test Job',
        'job_type' => AnonymizationJobs::TYPE_FULL,
        'status' => AnonymizationJobs::STATUS_DRAFT,
        'output_format' => AnonymizationJobs::OUTPUT_SQL,
        'seed_store_mode' => 'persistent',
        'seed_store_schema' => 'SBLSEED',
        'seed_store_prefix' => 'KLAMM',
        'job_seed' => "abc'123",
        'pre_mask_sql' => 'BEGIN NULL; END; /',
        'post_mask_sql' => 'BEGIN NULL; END; /',
    ]);

    $columns = AnonymousSiebelColumn::query()
        ->with([
            'anonymizationMethods.packages',
            'table.schema.database',
            'parentColumns.table.schema.database',
            'dataType',
        ])
        ->whereIn('id', [$this->providerId, $this->consumerId])
        ->get();

    $service = app(AnonymizationJobScriptService::class);
    $sql = $service->buildFromColumns($columns, $job);

    expect($sql)->toContain('MERGE INTO SBLSEED.');
    expect($sql)->toContain('WHEN NOT MATCHED THEN');
    expect($sql)->toContain("STANDARD_HASH(tgt.INTEGRATION_ID || 'abc''123'");

    // Pre/post blocks included.
    expect($sql)->toContain('-- Pre-mask SQL');
    expect($sql)->toContain('-- Post-mask SQL');
});

test('topological sort ensures seed provider columns are processed before consumers', function () {
    // This test verifies that columns are processed in dependency order:
    // INTEGRATION_ID (provider) should appear before PAR_ROW_ID (consumer) in the SQL output.

    $job = AnonymizationJobs::query()->create([
        'name' => 'Dependency Order Test',
        'job_type' => AnonymizationJobs::TYPE_FULL,
        'status' => AnonymizationJobs::STATUS_DRAFT,
        'output_format' => AnonymizationJobs::OUTPUT_SQL,
        'seed_store_mode' => 'temporary',
    ]);

    $columns = AnonymousSiebelColumn::query()
        ->with([
            'anonymizationMethods.packages',
            'table.schema.database',
            'parentColumns.table.schema.database',
            'childColumns.table.schema.database',
            'dataType',
        ])
        ->whereIn('id', [$this->providerId, $this->consumerId])
        ->get();

    $service = app(AnonymizationJobScriptService::class);
    $sql = $service->buildFromColumns($columns, $job);

    // DEBUG: Output the SQL to see the order
    // dump($sql);

    // Find the position of the column masking annotations (not header/table references).
    // Look for "-- Column: " prefix to avoid matching column names in table clone statements.
    $providerPos = strpos($sql, '-- Column: SBLDEV.SIEBEL.S_CONTACT.INTEGRATION_ID');
    $consumerPos = strpos($sql, '-- Column: SBLDEV.SIEBEL.S_CONTACT.PAR_ROW_ID');

    // The provider (INTEGRATION_ID) should come BEFORE the consumer (PAR_ROW_ID).
    expect($providerPos)->toBeLessThan($consumerPos)
        ->and($providerPos)->not->toBeFalse()
        ->and($consumerPos)->not->toBeFalse();

    // Also verify the dependency is noted in the output.
    expect($sql)->toContain('depends on:');
});

test('columns with same method maintain dependency order', function () {
    // Create multiple columns with same method but dependencies to verify order is maintained.
    $db = AnonymousSiebelDatabase::query()->where('database_name', 'SBLDEV')->first();
    $schema = AnonymousSiebelSchema::query()->where('schema_name', 'SIEBEL')->first();
    $table = AnonymousSiebelTable::query()->where('table_name', 'S_CONTACT')->first();
    $varchar = AnonymousSiebelDataType::query()->where('data_type_name', 'Varchar')->first();

    // Create a chain: A -> B -> C (all using same method).
    $colA = AnonymousSiebelColumn::query()->create([
        'table_id' => $table->id,
        'data_type_id' => $varchar->id,
        'column_name' => 'CHAIN_A',
        'data_length' => 30,
        'nullable' => true,
        'content_hash' => md5('CHAIN_A'),
    ]);

    $colB = AnonymousSiebelColumn::query()->create([
        'table_id' => $table->id,
        'data_type_id' => $varchar->id,
        'column_name' => 'CHAIN_B',
        'data_length' => 30,
        'nullable' => true,
        'content_hash' => md5('CHAIN_B'),
    ]);

    $colC = AnonymousSiebelColumn::query()->create([
        'table_id' => $table->id,
        'data_type_id' => $varchar->id,
        'column_name' => 'CHAIN_C',
        'data_length' => 30,
        'nullable' => true,
        'content_hash' => md5('CHAIN_C'),
    ]);

    // Wire dependencies: A -> B -> C.
    DB::table('anonymous_siebel_column_dependencies')->insert([
        [
            'parent_field_id' => $colA->id,
            'child_field_id' => $colB->id,
            'is_seed_mandatory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'parent_field_id' => $colB->id,
            'child_field_id' => $colC->id,
            'is_seed_mandatory' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Create a single method used by all columns.
    $sharedMethod = AnonymizationMethods::query()->create([
        'name' => 'Shared Method',
        'category' => AnonymizationMethods::CATEGORY_DETERMINISTIC_MASKING,
        'sql_block' => "UPDATE {{TABLE}} tgt SET {{COLUMN}} = 'masked';",
        'emits_seed' => false,
        'requires_seed' => false,
    ]);

    // Attach same method to all columns.
    DB::table('anonymization_method_column')->insert([
        ['method_id' => $sharedMethod->id, 'column_id' => $colA->id, 'created_at' => now(), 'updated_at' => now()],
        ['method_id' => $sharedMethod->id, 'column_id' => $colB->id, 'created_at' => now(), 'updated_at' => now()],
        ['method_id' => $sharedMethod->id, 'column_id' => $colC->id, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $job = AnonymizationJobs::query()->create([
        'name' => 'Chain Order Test',
        'job_type' => AnonymizationJobs::TYPE_FULL,
        'status' => AnonymizationJobs::STATUS_DRAFT,
        'output_format' => AnonymizationJobs::OUTPUT_SQL,
        'seed_store_mode' => 'temporary',
    ]);

    $columns = AnonymousSiebelColumn::query()
        ->with([
            'anonymizationMethods.packages',
            'table.schema.database',
            'parentColumns.table.schema.database',
            'childColumns.table.schema.database',
            'dataType',
        ])
        ->whereIn('id', [$colA->id, $colB->id, $colC->id])
        ->get();

    $service = app(AnonymizationJobScriptService::class);
    $sql = $service->buildFromColumns($columns, $job);

    // Find positions.
    $posA = strpos($sql, 'CHAIN_A');
    $posB = strpos($sql, 'CHAIN_B');
    $posC = strpos($sql, 'CHAIN_C');

    // Order must be A -> B -> C.
    expect($posA)->toBeLessThan($posB)
        ->and($posB)->toBeLessThan($posC)
        ->and($posA)->not->toBeFalse()
        ->and($posB)->not->toBeFalse()
        ->and($posC)->not->toBeFalse();
});
