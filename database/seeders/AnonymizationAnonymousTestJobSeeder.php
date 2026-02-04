<?php

namespace Database\Seeders;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelDataType;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Services\Anonymizer\AnonymizationJobScriptService;
use Illuminate\Database\Seeder;

class AnonymizationAnonymousTestJobSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure required anonymization methods exist.
        $this->call([
            AnonymizationSqlOnlyMethodSeeder::class,
        ]);

        $varchar2 = AnonymousSiebelDataType::withTrashed()->firstOrCreate(
            ['data_type_name' => 'VARCHAR2'],
            ['description' => 'Oracle VARCHAR2']
        );
        if ($varchar2->trashed()) {
            $varchar2->restore();
        }

        $dateType = AnonymousSiebelDataType::withTrashed()->firstOrCreate(
            ['data_type_name' => 'DATE'],
            ['description' => 'Oracle DATE']
        );
        if ($dateType->trashed()) {
            $dateType->restore();
        }

        // Match the export script output.
        $database = AnonymousSiebelDatabase::withTrashed()->updateOrCreate(
            // For the Oracle demo seed schema, keep this aligned with the actual schema name
            // so generated SQL refers to a real owner (ANON_SCHEMA_SEED.sql creates INITIAL_* there).
            ['database_name' => 'ANON_SIEBEL'],
            [
                'description' => 'Seeded metadata database (Oracle owner) used for anonymization job demos.',
                'content_hash' => hash('sha256', 'ANON_SIEBEL'),
                'last_synced_at' => now(),
                'changed_at' => null,
                'changed_fields' => null,
            ]
        );
        if ($database->trashed()) {
            $database->restore();
        }

        $schema = AnonymousSiebelSchema::withTrashed()->updateOrCreate(
            // This must match the Oracle schema that owns INITIAL_* tables in ANON_SCHEMA_SEED.sql.
            ['schema_name' => 'ANON_SIEBEL'],
            [
                'database_id' => $database->getKey(),
                'description' => 'Seeded metadata schema (Oracle owner) for anonymization job demos.',
                'type' => 'oracle',
                'content_hash' => hash('sha256', 'ANON_SIEBEL:ANON_SIEBEL'),
                'last_synced_at' => now(),
                'changed_at' => null,
                'changed_fields' => null,
            ]
        );
        if ($schema->trashed()) {
            $schema->restore();
        }

        $table = AnonymousSiebelTable::withTrashed()->updateOrCreate(
            ['schema_id' => $schema->getKey(), 'table_name' => 'INITIAL_S_CONTACT'],
            [
                'object_type' => 'table',
                'table_comment' => 'Seeded contact table metadata for name/email/birthdate anonymization demo.',
                'content_hash' => hash('sha256', 'Anonymous_Test:Anonymous_Test:INITIAL_S_CONTACT'),
                'last_synced_at' => now(),
                'changed_at' => null,
                'changed_fields' => null,
            ]
        );
        if ($table->trashed()) {
            $table->restore();
        }

        // Seed minimal referenced metadata so RELATED_COLUMNS targets exist.
        $userTable = AnonymousSiebelTable::withTrashed()->updateOrCreate(
            ['schema_id' => $schema->getKey(), 'table_name' => 'INITIAL_S_USER'],
            [
                'object_type' => 'table',
                'table_comment' => 'Seeded user table metadata referenced by INITIAL_S_CONTACT relationships.',
                'content_hash' => hash('sha256', 'Anonymous_Test:Anonymous_Test:INITIAL_S_USER'),
                'last_synced_at' => now(),
                'changed_at' => null,
                'changed_fields' => null,
            ]
        );
        if ($userTable->trashed()) {
            $userTable->restore();
        }

        $this->upsertColumn(
            tableId: (int) $userTable->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'ROW_ID',
            length: 15,
            nullable: false,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::SOURCE,
            relatedColumnsRaw: null,
        );

        $this->upsertColumn(
            tableId: (int) $userTable->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'LOGIN',
            length: 50,
            nullable: true,
            anonymizationRequired: false,
            seedContractMode: SeedContractMode::NONE,
            relatedColumnsRaw: null,
        );

        /** @var AnonymousSiebelColumn $userRowId */
        $userRowId = AnonymousSiebelColumn::withTrashed()
            ->where('table_id', (int) $userTable->getKey())
            ->where('column_name', 'ROW_ID')
            ->firstOrFail();

        // Seed expression can reference job seed placeholders; the script generator resolves them.
        $userRowId->forceFill([
            'seed_contract_expression' => "substr(lower(rawtohex(standard_hash({{JOB_SEED_LITERAL}} || '|ROW_ID|' || tgt.ROW_ID, 'SHA256'))), 1, 15)",
            'seed_contract_notes' => 'Deterministically remap INITIAL_S_USER.ROW_ID so dependent FKs can be remapped via seed maps.',
        ])->save();

        // Seed provider: ROW_ID.
        // This mirrors how the job generator resolves {{SEED_EXPR}}:
        // consumer columns (that require seed) will use the provider's seed_contract_expression.
        $rowId = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'ROW_ID',
            length: 15,
            nullable: false,
            anonymizationRequired: false,
            seedContractMode: SeedContractMode::SOURCE,
            relatedColumnsRaw: null,
        );

        $rowId->forceFill([
            'seed_contract_expression' => 'tgt.ROW_ID',
            'seed_contract_notes' => 'Use ROW_ID as deterministic seed provider for name/email/date masking within INITIAL_S_CONTACT.',
        ])->save();

        // Primary demo columns: name, email, birthdate.
        $firstName = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'FST_NAME',
            length: 50,
            nullable: false,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: null,
        );

        $lastName = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'LAST_NAME',
            length: 50,
            nullable: false,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: null,
        );

        $email = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'EMAIL_ADDR',
            length: 100,
            nullable: true,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: null,
        );

        $altEmail = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'ALT_EMAIL_ADDR',
            length: 100,
            nullable: true,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: null,
        );

        $birthDate = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $dateType->getKey(),
            columnName: 'BIRTH_DT',
            length: 7,
            nullable: true,
            anonymizationRequired: true,
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: null,
        );

        // A related/dependent column (from the metadata export): CREATOR_LOGIN relates to INITIAL_S_USER.LOGIN.
        // We anonymize it with a deterministic irreversible token.
        $creatorLogin = $this->upsertColumn(
            tableId: (int) $table->getKey(),
            dataTypeId: (int) $varchar2->getKey(),
            columnName: 'CREATOR_LOGIN',
            length: 50,
            nullable: true,
            anonymizationRequired: true,
            // In the seeded Oracle demo data, CREATOR_LOGIN contains INITIAL_S_USER.LOGIN.
            // We will pre-mask rewrite it into INITIAL_S_USER.ROW_ID, then remap via seed map.
            seedContractMode: SeedContractMode::CONSUMER,
            relatedColumnsRaw: 'ANON_SIEBEL.INITIAL_S_USER.ROW_ID via LINK_CREATOR_LOGIN',
        );

        // Clear existing edges on reruns so we don't accumulate stale dependencies.
        $creatorLogin->parentColumns()->sync([]);

        // Seed dependency edges: consumer columns depend on ROW_ID.
        // Use sync() (not syncWithoutDetaching) so reruns don't accumulate stale dependencies.
        $rowId->childColumns()->sync([
            (int) $firstName->getKey() => ['is_seed_mandatory' => true],
            (int) $lastName->getKey() => ['is_seed_mandatory' => true],
            (int) $email->getKey() => ['is_seed_mandatory' => true],
            (int) $altEmail->getKey() => ['is_seed_mandatory' => true],
            (int) $birthDate->getKey() => ['is_seed_mandatory' => true],
        ]);

        // Cross-table dependency: CREATOR_LOGIN (after pre-mask rewrite) depends on INITIAL_S_USER.ROW_ID.
        $userRowId->childColumns()->syncWithoutDetaching([
            (int) $creatorLogin->getKey() => ['is_seed_mandatory' => true],
        ]);

        // Methods (seeded by AnonymizationSqlOnlyMethodSeeder).
        // Use SQL-only methods so the generated script runs in Oracle demo schemas
        // that cannot CREATE PACKAGE.
        $methodSeedProvider = AnonymizationMethods::query()->where('name', 'Seed Provider (No-Op)')->firstOrFail();
        $methodUserRowId = AnonymizationMethods::query()->where('name', 'SQL Deterministic Siebel ROW_ID (SHA-256)')->firstOrFail();
        $methodFirst = AnonymizationMethods::query()->where('name', 'SQL Deterministic First Name (SHA-256)')->firstOrFail();
        $methodLast = AnonymizationMethods::query()->where('name', 'SQL Deterministic Last Name (SHA-256)')->firstOrFail();
        $methodEmail = AnonymizationMethods::query()->where('name', 'SQL Deterministic Email (SHA-256)')->firstOrFail();
        $methodBirth = AnonymizationMethods::query()->where('name', 'SQL Date Shift (±365 days)')->firstOrFail();
        $methodFkLookup = AnonymizationMethods::query()->where('name', 'SQL Seed Map Lookup (FK)')->firstOrFail();

        // Ensure each demo column has exactly the intended method association.
        // This prevents method resolution from picking up stale/legacy associations.
        $rowId->anonymizationMethods()->sync([(int) $methodSeedProvider->getKey()]);
        $userRowId->anonymizationMethods()->sync([(int) $methodUserRowId->getKey()]);
        $firstName->anonymizationMethods()->sync([(int) $methodFirst->getKey()]);
        $lastName->anonymizationMethods()->sync([(int) $methodLast->getKey()]);
        $email->anonymizationMethods()->sync([(int) $methodEmail->getKey()]);
        $altEmail->anonymizationMethods()->sync([(int) $methodEmail->getKey()]);
        $birthDate->anonymizationMethods()->sync([(int) $methodBirth->getKey()]);
        $creatorLogin->anonymizationMethods()->sync([(int) $methodFkLookup->getKey()]);

        $job = AnonymizationJobs::withTrashed()->updateOrCreate(
            ['name' => 'Demo: Anonymous_Test Contact PII Masking'],
            [
                'job_type' => AnonymizationJobs::TYPE_PARTIAL,
                'status' => AnonymizationJobs::STATUS_DRAFT,
                'output_format' => AnonymizationJobs::OUTPUT_SQL,
                // Ensure the demo job executes against the same schema that owns the seeded INITIAL_* tables.
                'target_schema' => 'ANON_SIEBEL',
                // Mirror INITIAL_* -> ANON_* (e.g., INITIAL_S_CONTACT -> ANON_S_CONTACT).
                'target_table_mode' => 'anon',
                'seed_store_mode' => 'temporary',
                'seed_map_hygiene_mode' => 'commented',
                'seed_store_schema' => null,
                'seed_store_prefix' => null,
                'job_seed' => 'anonymous-test-seed',
                // Convert CREATOR_LOGIN from LOGIN -> ROW_ID before building seed maps and remapping.
                // This demonstrates a cross-table relationship where the final ANON_S_CONTACT.CREATOR_LOGIN
                // references the remapped ANON_S_USER.ROW_ID.
                'pre_mask_sql' => "update ANON_S_CONTACT c\n   set c.CREATOR_LOGIN = (select u.ROW_ID from ANON_S_USER u where u.LOGIN = c.CREATOR_LOGIN and rownum = 1)\n where c.CREATOR_LOGIN is not null;",
                'post_mask_sql' => null,
                'last_run_at' => null,
                'duration_seconds' => null,
            ]
        );
        if ($job->trashed()) {
            $job->restore();
        }

        // Scope selections.
        $job->databases()->sync([(int) $database->getKey()]);
        $job->schemas()->sync([(int) $schema->getKey()]);
        $job->tables()->sync([
            (int) $table->getKey(),
            (int) $userTable->getKey(),
        ]);

        // Explicit job selection + chosen method ids.
        // ROW_ID uses a no-op method that is marked as emits_seed so seed-contract validation is clean.
        $job->columns()->sync([
            (int) $rowId->getKey() => ['anonymization_method_id' => (int) $methodSeedProvider->getKey()],
            (int) $userRowId->getKey() => ['anonymization_method_id' => (int) $methodUserRowId->getKey()],
            (int) $firstName->getKey() => ['anonymization_method_id' => (int) $methodFirst->getKey()],
            (int) $lastName->getKey() => ['anonymization_method_id' => (int) $methodLast->getKey()],
            (int) $email->getKey() => ['anonymization_method_id' => (int) $methodEmail->getKey()],
            (int) $altEmail->getKey() => ['anonymization_method_id' => (int) $methodEmail->getKey()],
            (int) $birthDate->getKey() => ['anonymization_method_id' => (int) $methodBirth->getKey()],
            (int) $creatorLogin->getKey() => ['anonymization_method_id' => (int) $methodFkLookup->getKey()],
        ]);

        // Pre-build sql_script so the job is immediately testable.
        $job->refresh();
        $sql = app(AnonymizationJobScriptService::class)->buildForJob($job);
        $job->forceFill(['sql_script' => $sql])->save();
    }

    private function upsertColumn(
        int $tableId,
        int $dataTypeId,
        string $columnName,
        int $length,
        bool $nullable,
        bool $anonymizationRequired,
        SeedContractMode $seedContractMode,
        ?string $relatedColumnsRaw,
    ): AnonymousSiebelColumn {
        $col = AnonymousSiebelColumn::withTrashed()->updateOrCreate(
            ['table_id' => $tableId, 'column_name' => $columnName],
            [
                'column_id' => null,
                'data_length' => $length,
                'data_precision' => null,
                'data_scale' => null,
                'nullable' => $nullable,
                'char_length' => $length,
                'column_comment' => null,
                'table_comment' => null,
                'related_columns_raw' => $relatedColumnsRaw,
                'related_columns' => null,
                'content_hash' => hash('sha256', "Anonymous_Test:INITIAL_S_CONTACT:{$columnName}:{$length}:" . ($nullable ? 'Y' : 'N')),
                'last_synced_at' => now(),
                'changed_at' => null,
                'changed_fields' => null,
                'data_type_id' => $dataTypeId,
                'metadata_comment' => 'Seeded from demo export to showcase job-driven anonymization.',
                'anonymization_required' => $anonymizationRequired,
                'seed_contract_mode' => $seedContractMode,
                'seed_contract_expression' => null,
                'seed_contract_notes' => null,
            ]
        );

        if ($col->trashed()) {
            $col->restore();
        }

        return $col;
    }
}
