<?php

namespace App\Services\Anonymizer\Concerns;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

use function Symfony\Component\Clock\now;

// Builds deterministic Oracle anonymization scripts using a double-seeded approach.
// The double-seeded strategy ensures:
// 1. Reproducibility: Same job seed + original values produce identical masked results.
// 2. Referential integrity: FK relationships are preserved via seed maps populated before dependents.
// 3. Dependency ordering: Parent/seed-providing columns are masked before their consumers.
trait BuildsDoubleSeededDeterministicOracleScripts
{
    public function buildDoubleSeededDeterministicFromColumns(Collection $columns, AnonymizationJobs $job): string
    {
        if ($columns->isEmpty()) {
            return '';
        }

        if (method_exists($columns, 'loadMissing')) {
            // Load core relations first
            $columns->loadMissing([
                'anonymizationMethods',
                'table.schema.database',
                'dataType',
                'parentColumns',
                'childColumns',
            ]);

            // Load packages on the already-loaded methods collection
            $methods = $columns->pluck('anonymizationMethods')->flatten()->filter()->unique('id');
            if ($methods->isNotEmpty()) {
                $methods->loadMissing('packages');
            }
        }

        $ordered = $this->topologicallySortColumns($columns);
        if ($ordered->isEmpty()) {
            return '';
        }

        $rewriteContext = $this->buildJobTableRewriteContext($ordered, $job);
        $targetSchema = (string) ($rewriteContext['target_schema'] ?? '');
        if ($targetSchema === '') {
            return '-- No SQL generated: unable to resolve job target schema.';
        }

        $seedPrefix = trim((string) ($job->seed_store_prefix ?? ''));
        if ($seedPrefix === '') {
            $seedPrefix = 'JOB';
        }
        $seedPrefix = $this->oracleIdentifier(Str::upper($seedPrefix));

        $seedStoreSchema = trim((string) ($job->seed_store_schema ?? ''));
        if ($seedStoreSchema === '') {
            $seedStoreSchema = $targetSchema;
        }

        $jobKeyName = $this->oracleIdentifier(Str::upper((string) $job->name));
        $jobSeedLiteral = $this->oracleStringLiteral($job->job_seed);
        $jobSeedTable = $seedStoreSchema . '.' . $this->oracleIdentifier($seedPrefix . '_JOB_SEED');

        // Build seed providers map and seed map context for cross-table FK preservation.
        $seedProviders = $this->resolveSeedProviders($ordered);
        $seedMapContext = $this->buildSeedMapContext($ordered, $seedProviders, $rewriteContext, $job);

        // Collect tables involved and map to working copies.
        $tables = $this->collectTablesForDoubleSeededJob($ordered);
        $tableMappings = $this->buildStableDemoTableMappings($tables, $targetSchema, $seedPrefix);
        $tableMappingsBySourceTable = collect($tableMappings)->keyBy('source_table');

        // Identify seed provider columns that need ORIGINAL_<column> tracking for deterministic masking.
        $seedProviderColumns = $this->identifySeedProviderColumns($ordered, $seedMapContext);

        $lines = [];

        // === Header ===
        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Anonymization Job: ' . $job->name;
        $lines[] = '-- Generated: ' . now()->format('Y-m-d H:i:s');
        $lines[] = '-- Job Type: Deterministic (Double-Seeded)';
        $lines[] = '-- Target DB: Oracle';
        $lines[] = '-- Columns: ' . $ordered->count();
        $lines[] = '-- Tables: ' . count($tableMappings);
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        // === Execution context ===
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- EXECUTION CONTEXT';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'ALTER SESSION SET CURRENT_SCHEMA = ' . $targetSchema . ';';
        $lines[] = '';

        // === Global job seed table ===
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- GLOBAL JOB SEED';
        $lines[] = '-- Controls run-to-run reproducibility';
        $lines[] = $this->commentDivider('-');
        $lines = array_merge($lines, $this->renderJobSeedTableDDL($jobSeedTable, $jobKeyName, $jobSeedLiteral));

        // === Packages ===
        $packages = $this->collectPackagesFromColumns($ordered);
        if ($packages->isNotEmpty()) {
            $lines[] = $this->commentDivider('-');
            $lines[] = '-- DETERMINISTIC HELPER PACKAGES';
            $lines[] = '-- Hash-based functions (no DBMS_RANDOM)';
            $lines[] = $this->commentDivider('-');

            foreach ($packages as $package) {
                foreach ($package->compiledSqlBlocks() as $block) {
                    $rewritten = $this->rewritePackageSqlBlock((string) $block, $rewriteContext);
                    $rewritten = $this->applyJobPlaceholdersToSql($rewritten, [
                        '{{JOB_NAME}}' => $this->oracleStringLiteral($jobKeyName),
                        '{{JOB_SEED_LITERAL}}' => $jobSeedLiteral,
                        '{{JOB_SEED_TABLE}}' => $jobSeedTable,
                        '{{TARGET_SCHEMA}}' => $targetSchema,
                        '{{SEED_STORE_SCHEMA}}' => $seedStoreSchema,
                        '{{SEED_PREFIX}}' => $seedPrefix,
                    ]);
                    $lines = array_merge($lines, preg_split('/\R/', trim($rewritten)) ?: []);
                    $lines[] = '';
                }
            }
        }

        // === Working copy creation ===
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- WORKING COPY CREATION';
        $lines[] = '-- Clones source tables to isolated working copies.';
        $lines[] = '-- Adds ORIGINAL_<column> columns for seed provider tracking.';
        $lines[] = $this->commentDivider('-');

        foreach ($tableMappings as $mapping) {
            $lines = array_merge($lines, $this->renderStableCloneStatements($mapping['source_qualified'], $mapping['target_qualified']));

            // Add ORIGINAL_<column> columns for each seed provider in this table.
            $tableProviders = $seedProviderColumns->filter(
                fn($p) => ($p['source_table'] ?? '') === ($mapping['source_table'] ?? '')
            );

            // Preserve original values so dependents can map back deterministically.
            foreach ($tableProviders as $provider) {
                $originalCol = 'ORIGINAL_' . Str::upper($provider['column_name']);
                $columnType = $provider['column_type'] ?? 'VARCHAR2(4000)';

                $lines[] = 'ALTER TABLE ' . $mapping['target_qualified'];
                $lines[] = 'ADD ' . $this->oracleIdentifier($originalCol) . ' ' . $columnType . ';';
                $lines[] = '';
                $lines[] = 'UPDATE ' . $mapping['target_qualified'];
                $lines[] = 'SET ' . $this->oracleIdentifier($originalCol) . ' = ' . $this->oracleIdentifier($provider['column_name']) . ';';
                $lines[] = '';
                $lines[] = 'COMMIT;';
                $lines[] = '';
            }
        }

        // === Seed maps for FK preservation ===
        $seedMaps = $this->buildDoubleSeededSeedMaps($seedProviderColumns, $tableMappingsBySourceTable, $seedStoreSchema, $seedPrefix);

        if ($seedMaps->isNotEmpty()) {
            $lines[] = $this->commentDivider('-');
            $lines[] = '-- CROSS-TABLE SEED MAPS';
            $lines[] = '-- Lookup tables preserve FK relationships between tables.';
            $lines[] = '-- Populated from ORIGINAL_<column> before parent key is masked.';
            $lines[] = $this->commentDivider('-');

            foreach ($seedMaps as $seedMap) {
                $lines = array_merge($lines, $this->renderSeedMapDDLAndPopulate(
                    $seedMap,
                    $jobSeedTable,
                    $jobKeyName
                ));
            }
        }

        // === Column masking in topological order ===
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- COLUMN MASKING (dependency-ordered)';
        $lines[] = '-- Parent/seed-providing columns are masked after dependents read their values.';
        $lines[] = '-- FK consumers use seed maps to maintain referential integrity.';
        $lines[] = $this->commentDivider('-');

        // Separate columns into: non-provider dependents first, then seed providers last.
        $nonProviderColumns = $ordered->filter(fn($c) => ! $seedProviderColumns->has($c->id));
        $providerColumnModels = $ordered->filter(fn($c) => $seedProviderColumns->has($c->id));

        // Mask non-provider columns first (they may depend on seed maps).
        if ($nonProviderColumns->isNotEmpty()) {
            $lines[] = '-- Dependent columns (masked first, before their seed providers change)';
            $lines = array_merge($lines, $this->renderDoubleSeededColumnMasking(
                $nonProviderColumns,
                $seedMaps,
                $tableMappingsBySourceTable,
                $jobSeedTable,
                $jobKeyName,
                $seedProviderColumns
            ));
        }

        // Mask seed provider columns last (after seed maps are populated and dependents are updated).
        if ($providerColumnModels->isNotEmpty()) {
            $lines[] = '-- Seed provider columns (masked last, after dependents use original values)';
            $lines = array_merge($lines, $this->renderDoubleSeededColumnMasking(
                $providerColumnModels,
                $seedMaps,
                $tableMappingsBySourceTable,
                $jobSeedTable,
                $jobKeyName,
                $seedProviderColumns
            ));
        }

        $pkStatements = $this->renderDeterministicPrimaryKeys($tableMappings);
        if ($pkStatements !== []) {
            $lines[] = $this->commentDivider('-');
            $lines[] = '-- PRIMARY KEYS';
            $lines[] = '-- Add ROW_ID primary keys so foreign keys can be recreated.';
            $lines[] = $this->commentDivider('-');
            $lines = array_merge($lines, $pkStatements);
        }

        $fkStatements = $this->renderDeterministicForeignKeys($tableMappings);
        if ($fkStatements !== []) {
            $lines[] = $this->commentDivider('-');
            $lines[] = '-- FOREIGN KEYS';
            $lines[] = '-- Recreate parent/child relationships within the target schema.';
            $lines[] = $this->commentDivider('-');
            $lines = array_merge($lines, $fkStatements);
        }

        // === Finalization ===
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- END OF JOB';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'COMMIT;';
        $lines[] = 'prompt ' . $seedPrefix . ' Deterministic Masking complete';

        return trim(implode(PHP_EOL, $lines));
    }

    // Identify columns that act as seed providers (have dependents that are also selected).
    protected function identifySeedProviderColumns(Collection $columns, array $seedMapContext): Collection
    {
        $providers = collect();
        $selectedIds = $columns->pluck('id')->flip();

        foreach ($columns as $column) {
            $mode = $column->seed_contract_mode;
            $childColumns = $column->getRelationValue('childColumns') ?? collect();

            // A column is a seed provider if:
            // 1. It's explicitly marked as SOURCE/COMPOSITE/EXTERNAL, or
            // 2. It has child columns that are selected in this job.
            $hasSelectedDependents = $childColumns->filter(fn($c) => $selectedIds->has($c->id))->isNotEmpty();
            $isExplicitProvider = in_array($mode, [SeedContractMode::SOURCE, SeedContractMode::COMPOSITE, SeedContractMode::EXTERNAL], true);
            $isInSeedMapContext = isset(($seedMapContext['providers'] ?? [])[$column->id]);

            if ($isExplicitProvider || $hasSelectedDependents || $isInSeedMapContext) {
                $table = $column->getRelationValue('table');
                $providers->put($column->id, [
                    'column_id' => $column->id,
                    'column_name' => $column->column_name,
                    'column_type' => $this->oracleColumnTypeForColumn($column),
                    'source_table' => $table?->table_name,
                    'table_id' => $table?->getKey(),
                    'has_dependents' => $hasSelectedDependents,
                    'is_explicit' => $isExplicitProvider,
                ]);
            }
        }

        return $providers;
    }

    // Build seed map definitions for cross-table FK preservation.
    protected function buildDoubleSeededSeedMaps(Collection $seedProviderColumns, Collection $tableMappings, string $seedStoreSchema, string $seedPrefix): Collection
    {
        return $seedProviderColumns->map(function ($provider) use ($tableMappings, $seedStoreSchema, $seedPrefix) {
            $sourceTable = $provider['source_table'] ?? null;
            $mapping = $sourceTable ? $tableMappings->get($sourceTable) : null;

            if (! $mapping) {
                return null;
            }

            $columnName = $provider['column_name'];
            $seedMapName = $this->oracleIdentifier($seedPrefix . '_SEEDMAP_' . Str::upper($sourceTable) . '_' . Str::upper($columnName));

            return [
                'seed_map_table' => $seedStoreSchema . '.' . $seedMapName,
                'provider_column' => $columnName,
                'original_column' => 'ORIGINAL_' . Str::upper($columnName),
                'provider_table' => $mapping['target_qualified'],
                'source_table' => $sourceTable,
                'column_type' => $provider['column_type'] ?? 'VARCHAR2(4000)',
                'column_id' => $provider['column_id'],
            ];
        })->filter()->values();
    }

    // Render DDL for job seed table.
    protected function renderJobSeedTableDDL(string $jobSeedTable, string $jobKeyName, string $jobSeedLiteral): array
    {
        return [
            'BEGIN',
            "  EXECUTE IMMEDIATE '",
            '    CREATE TABLE ' . $jobSeedTable . ' (',
            '      job_name VARCHAR2(64) PRIMARY KEY,',
            '      job_seed VARCHAR2(128) NOT NULL',
            "    )'",
            ';',
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -955 THEN RAISE; END IF;',
            'END;',
            '/',
            '',
            'MERGE INTO ' . $jobSeedTable . ' t',
            'USING (',
            '  SELECT',
            '    ' . $this->oracleStringLiteral($jobKeyName) . ' job_name,',
            '    ' . $jobSeedLiteral . ' job_seed',
            '  FROM dual',
            ') s',
            'ON (t.job_name = s.job_name)',
            'WHEN MATCHED THEN',
            '  UPDATE SET t.job_seed = s.job_seed',
            'WHEN NOT MATCHED THEN',
            '  INSERT (job_name, job_seed)',
            '  VALUES (s.job_name, s.job_seed);',
            '',
            'COMMIT;',
            '',
        ];
    }

    // Render DDL for a seed map table and populate it from original values.
    protected function renderSeedMapDDLAndPopulate(array $seedMap, string $jobSeedTable, string $jobKeyName): array
    {
        $seedMapTable = $seedMap['seed_map_table'];
        $providerTable = $seedMap['provider_table'];
        $originalCol = $this->oracleIdentifier($seedMap['original_column']);
        $columnType = $seedMap['column_type'];
        $columnLabel = Str::upper($seedMap['provider_column']);

        return [
            '-- Seed map for: ' . $seedMap['source_table'] . '.' . $seedMap['provider_column'],
            'BEGIN',
            "  EXECUTE IMMEDIATE 'CREATE TABLE {$seedMapTable} (old_value {$columnType} PRIMARY KEY, new_value {$columnType})';",
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -955 THEN RAISE; END IF;',
            'END;',
            '/',
            '',
            'DECLARE',
            '  v_job_seed VARCHAR2(128);',
            'BEGIN',
            '  SELECT job_seed INTO v_job_seed',
            '  FROM ' . $jobSeedTable,
            '  WHERE job_name = ' . $this->oracleStringLiteral($jobKeyName) . ';',
            '',
            '  MERGE INTO ' . $seedMapTable . ' sm',
            '  USING (',
            '    SELECT DISTINCT',
            '      tgt.' . $originalCol . ' AS old_value,',
            "      SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH(v_job_seed || '|{$columnLabel}|' || tgt.{$originalCol}, 'SHA256'))), 1, " . min(64, $this->oracleColumnMaxLength($seedMap)) . ') AS new_value',
            '    FROM ' . $providerTable . ' tgt',
            '    WHERE tgt.' . $originalCol . ' IS NOT NULL',
            '  ) src',
            '  ON (sm.old_value = src.old_value)',
            '  WHEN MATCHED THEN',
            '    UPDATE SET sm.new_value = src.new_value',
            '  WHEN NOT MATCHED THEN',
            '    INSERT (old_value, new_value) VALUES (src.old_value, src.new_value);',
            'END;',
            '/',
            'COMMIT;',
            '',
        ];
    }

    // Render column masking statements for double-seeded approach.
    protected function renderDoubleSeededColumnMasking(
        Collection $columns,
        Collection $seedMaps,
        Collection $tableMappings,
        string $jobSeedTable,
        string $jobKeyName,
        Collection $seedProviderColumns
    ): array {
        $lines = [];
        $seedMapsByColumnId = $seedMaps->keyBy('column_id');

        foreach ($columns as $column) {
            $table = $column->getRelationValue('table');
            $sourceTable = $table?->table_name;
            $mapping = $sourceTable ? $tableMappings->get($sourceTable) : null;

            if (! $mapping) {
                $lines[] = '-- SKIPPED: ' . $this->describeColumn($column) . ' (no table mapping)';
                continue;
            }

            $targetTable = $mapping['target_qualified'];
            $columnName = $this->oracleIdentifier($column->column_name);
            $method = $this->resolveMethodForColumn($column);
            $sqlBlock = trim((string) ($method?->sql_block ?? ''));
            $isProvider = $seedProviderColumns->has($column->id);

            // Annotate with dependency info.
            $parents = $column->getRelationValue('parentColumns') ?? collect();
            $parentNames = $parents->map(fn($p) => $this->describeColumn($p))->implode(', ');
            $depNote = $parentNames ? " (depends on: {$parentNames})" : '';

            $lines[] = '-- Column: ' . $this->describeColumn($column) . $depNote;

            if ($isProvider) {
                // Seed provider: update using seed map lookup.
                $seedMap = $seedMapsByColumnId->get($column->id);

                if ($seedMap) {
                    $seedMapTable = $seedMap['seed_map_table'];
                    $originalCol = $this->oracleIdentifier($seedMap['original_column']);

                    $lines[] = 'UPDATE ' . $targetTable . ' t';
                    $lines[] = 'SET t.' . $columnName . ' =';
                    $lines[] = '  (SELECT sm.new_value';
                    $lines[] = '   FROM ' . $seedMapTable . ' sm';
                    $lines[] = '   WHERE sm.old_value = t.' . $originalCol . ');';
                    $lines[] = '';
                    $lines[] = 'COMMIT;';
                } elseif ($sqlBlock !== '') {
                    // Provider without a seed map: use method SQL block.
                    $lines[] = $this->renderSingleColumnUpdate($column, $targetTable, $sqlBlock, $jobSeedTable, $jobKeyName, $seedProviderColumns);
                    $lines[] = 'COMMIT;';
                } else {
                    $lines[] = '-- No SQL block or seed map defined.';
                }
            } else {
                // Dependent column: check if it should use a parent's seed map.
                $parentSeedMap = $this->findParentSeedMapForColumn($column, $seedMapsByColumnId);

                if ($parentSeedMap) {
                    // FK consumer: use parent's seed map for lookup.
                    $seedMapTable = $parentSeedMap['seed_map_table'];

                    $lines[] = 'UPDATE ' . $targetTable . ' t';
                    $lines[] = 'SET t.' . $columnName . ' =';
                    $lines[] = '  (SELECT sm.new_value';
                    $lines[] = '   FROM ' . $seedMapTable . ' sm';
                    $lines[] = '   WHERE sm.old_value = t.' . $columnName . ');';
                    $lines[] = '';
                    $lines[] = 'COMMIT;';
                } elseif ($sqlBlock !== '') {
                    // Regular column: use method SQL block.
                    $lines[] = $this->renderSingleColumnUpdate($column, $targetTable, $sqlBlock, $jobSeedTable, $jobKeyName, $seedProviderColumns);
                    $lines[] = 'COMMIT;';
                } else {
                    $lines[] = '-- No SQL block defined for this method.';
                }
            }

            $lines[] = '';
        }

        return $lines;
    }

    // Find a parent's seed map that this column should use for FK preservation.
    protected function findParentSeedMapForColumn(AnonymousSiebelColumn $column, Collection $seedMapsByColumnId): ?array
    {
        $parents = $column->getRelationValue('parentColumns') ?? collect();

        foreach ($parents as $parent) {
            $seedMap = $seedMapsByColumnId->get($parent->id);
            if ($seedMap) {
                return $seedMap;
            }
        }

        return null;
    }

    // Render a single column UPDATE statement using the method's SQL block.
    protected function renderSingleColumnUpdate(
        AnonymousSiebelColumn $column,
        string $targetTable,
        string $sqlBlock,
        string $jobSeedTable,
        string $jobKeyName,
        Collection $seedProviderColumns
    ): string {
        $columnName = $this->oracleIdentifier($column->column_name);

        // Prefer ORIGINAL_<col> when a seed provider is involved to keep determinism.
        $originalRef = null;
        if ($seedProviderColumns->has($column->id)) {
            $originalRef = 'ORIGINAL_' . Str::upper($column->column_name);
        } else {
            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                if ($seedProviderColumns->has($parent->id)) {
                    $originalRef = 'ORIGINAL_' . Str::upper($parent->column_name);
                    break;
                }
            }
        }

        $seedRef = $originalRef ? "t.{$this->oracleIdentifier($originalRef)}" : "t.{$columnName}";

        // Apply standard placeholders.
        $rendered = str_replace(
            ['{{COLUMN_NAME}}', '{{TARGET_TABLE}}', '{{SEED_REF}}', '{{JOB_SEED_TABLE}}', '{{JOB_KEY_NAME}}'],
            [$columnName, $targetTable, $seedRef, $jobSeedTable, $this->oracleStringLiteral($jobKeyName)],
            $sqlBlock
        );

        // Wrap in PL/SQL block if it doesn't look like a complete statement.
        if (! preg_match('/^\s*(UPDATE|MERGE|DELETE|INSERT|BEGIN|DECLARE)/i', $rendered)) {
            $rendered = "UPDATE {$targetTable} t SET t.{$columnName} = {$rendered};";
        }

        return $rendered;
    }

    protected function oracleColumnMaxLength(array $seedMap): int
    {
        $type = strtoupper($seedMap['column_type'] ?? '');

        if (preg_match('/VARCHAR2?\s*\(\s*(\d+)\s*\)/i', $type, $m)) {
            return min((int) $m[1], 64);
        }

        return 64;
    }

    protected function collectTablesForDoubleSeededJob(Collection $columns): Collection
    {
        $tables = collect();

        foreach ($columns as $column) {
            $table = $column->getRelationValue('table');
            if ($table) {
                $tables->push($table);
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTable = $parent->getRelationValue('table');
                if ($parentTable) {
                    $tables->push($parentTable);
                }
            }
        }

        return $tables
            ->filter()
            ->unique(fn($t) => (int) $t->getKey())
            ->values();
    }

    // @param Collection<int, AnonymousSiebelTable> $tables
    protected function buildStableDemoTableMappings(Collection $tables, string $targetSchema, string $seedPrefix): array
    {
        $mappings = [];

        foreach ($tables as $table) {
            $schema = $table->getRelationValue('schema');
            $sourceSchema = $schema?->schema_name;
            $sourceTable = $table->table_name;

            if (! $sourceSchema || ! $sourceTable) {
                continue;
            }

            $sourceQualified = $sourceSchema . '.' . $sourceTable;
            $demoSuffix = $this->stableDemoSuffixForSourceTable($sourceTable);
            $targetTable = $this->oracleIdentifier('Demo_' . $seedPrefix . '_' . $demoSuffix);
            $targetQualified = $targetSchema . '.' . $targetTable;

            $mappings[] = [
                'table_id' => (int) $table->getKey(),
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
            ];
        }

        usort($mappings, fn($a, $b) => strcmp((string) ($a['source_table'] ?? ''), (string) ($b['source_table'] ?? '')));

        return $mappings;
    }

    protected function stableDemoSuffixForSourceTable(string $sourceTable): string
    {
        $normalized = Str::upper(trim($sourceTable));
        if (str_starts_with($normalized, 'S_')) {
            $normalized = substr($normalized, 2);
        }

        $parts = array_values(array_filter(explode('_', $normalized), fn($p) => $p !== ''));
        if ($parts === []) {
            return 'Table';
        }

        $rendered = [];
        foreach ($parts as $part) {
            if ($part === 'XM' || $part === 'XREF') {
                $rendered[] = $part;
                continue;
            }

            $rendered[] = Str::ucfirst(Str::lower($part));
        }

        return implode('_', $rendered);
    }

    protected function renderStableCloneStatements(string $qualifiedSource, string $qualifiedTarget): array
    {
        return [
            'BEGIN',
            "  EXECUTE IMMEDIATE 'DROP TABLE {$qualifiedTarget} CASCADE CONSTRAINTS PURGE';",
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -942 THEN RAISE; END IF;',
            'END;',
            '/',
            '',
            'CREATE TABLE ' . $qualifiedTarget . ' AS',
            'SELECT * FROM ' . $qualifiedSource . ';',
            '',
        ];
    }

    protected function renderDeterministicForeignKeys(array $tableMappings): array
    {
        if ($tableMappings === []) {
            return [];
        }

        $tableMapById = [];
        foreach ($tableMappings as $mapping) {
            $tableId = (int) ($mapping['table_id'] ?? 0);
            if ($tableId > 0) {
                $tableMapById[$tableId] = $mapping;
            }
        }

        if ($tableMapById === []) {
            return [];
        }

        $tableIds = array_keys($tableMapById);

        $columns = AnonymousSiebelColumn::query()
            ->with(['dataType', 'table.schema'])
            ->whereIn('table_id', $tableIds)
            ->get();

        if ($columns->isEmpty()) {
            return [];
        }

        $tablesByIdentity = [];
        foreach ($tableMapById as $tableId => $mapping) {
            $schema = strtoupper(trim((string) ($mapping['source_schema'] ?? '')));
            $table = strtoupper(trim((string) ($mapping['source_table'] ?? '')));
            if ($schema !== '' && $table !== '') {
                $tablesByIdentity[$schema . '|' . $table] = (int) $tableId;
            }
        }

        $columnsByTable = $columns->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

        $lines = [];
        $seen = [];

        foreach ($columns as $column) {
            $childTableId = (int) ($column->table_id ?? 0);
            if ($childTableId <= 0) {
                continue;
            }

            $childMap = $tableMapById[$childTableId] ?? null;
            if (! is_array($childMap)) {
                continue;
            }

            $childColumn = trim((string) ($column->column_name ?? ''));
            if ($childColumn === '' || $this->isLongColumn($column)) {
                continue;
            }

            $relationships = $this->resolveDeterministicForeignKeyRelationships($column);
            if ($relationships === []) {
                continue;
            }

            foreach ($relationships as $relationship) {
                $direction = strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND'));
                if ($direction === 'INBOUND') {
                    continue;
                }

                $schema = strtoupper(trim((string) ($relationship['schema'] ?? '')));
                $table = strtoupper(trim((string) ($relationship['table'] ?? '')));
                $parentColumn = trim((string) ($relationship['column'] ?? 'ROW_ID'));

                if ($schema === '' || $table === '' || $parentColumn === '') {
                    continue;
                }

                if (strtoupper($parentColumn) !== 'ROW_ID') {
                    continue;
                }

                $parentTableId = $tablesByIdentity[$schema . '|' . $table] ?? null;
                if (! $parentTableId) {
                    continue;
                }

                $parentMap = $tableMapById[$parentTableId] ?? null;
                if (! is_array($parentMap)) {
                    continue;
                }

                $parentColumns = $columnsByTable->get((int) $parentTableId, collect());
                $parentModel = $parentColumns
                    ->first(fn(AnonymousSiebelColumn $col) => strtoupper((string) ($col->column_name ?? '')) === strtoupper($parentColumn));

                if (! $parentModel || $this->isLongColumn($parentModel)) {
                    continue;
                }

                if (! $this->columnsAreCompatible($column, $parentModel)) {
                    continue;
                }

                $childTarget = $childMap['target_qualified'] ?? null;
                $parentTarget = $parentMap['target_qualified'] ?? null;

                if (! $childTarget || ! $parentTarget) {
                    continue;
                }

                $fingerprint = strtoupper($childTarget . '|' . $childColumn . '|' . $parentTarget . '|' . $parentColumn);
                if (isset($seen[$fingerprint])) {
                    continue;
                }
                $seen[$fingerprint] = true;

                $hash = substr(md5($fingerprint), 0, 8);
                $constraintName = $this->oracleIdentifier('FK_' . ($childMap['target_table'] ?? 'CHILD') . '_' . $hash);

                $lines[] = 'ALTER TABLE ' . $childTarget;
                $lines[] = 'ADD CONSTRAINT ' . $constraintName;
                $lines[] = 'FOREIGN KEY (' . $childColumn . ')';
                $lines[] = 'REFERENCES ' . $parentTarget . ' (' . $parentColumn . ')';
                $lines[] = 'ENABLE NOVALIDATE;';
                $lines[] = '';
            }
        }

        return $lines;
    }

    protected function renderDeterministicPrimaryKeys(array $tableMappings): array
    {
        if ($tableMappings === []) {
            return [];
        }

        $tableMapById = [];
        foreach ($tableMappings as $mapping) {
            $tableId = (int) ($mapping['table_id'] ?? 0);
            if ($tableId > 0) {
                $tableMapById[$tableId] = $mapping;
            }
        }

        if ($tableMapById === []) {
            return [];
        }

        $tableIds = array_keys($tableMapById);
        $columnsByTable = AnonymousSiebelColumn::query()
            ->with(['dataType'])
            ->whereIn('table_id', $tableIds)
            ->get()
            ->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

        $lines = [];
        $seen = [];

        foreach ($tableMapById as $tableId => $mapping) {
            $target = $mapping['target_qualified'] ?? null;
            if (! $target) {
                continue;
            }

            $columns = $columnsByTable->get((int) $tableId, collect());
            $rowId = $columns->first(fn(AnonymousSiebelColumn $column) => strtoupper((string) ($column->column_name ?? '')) === 'ROW_ID');
            if (! $rowId || $this->isLongColumn($rowId)) {
                continue;
            }

            $fingerprint = strtoupper($target . '|ROW_ID');
            if (isset($seen[$fingerprint])) {
                continue;
            }
            $seen[$fingerprint] = true;

            $constraintName = $this->oracleIdentifier('PK_' . ($mapping['target_table'] ?? ('TABLE_' . $tableId)));
            $lines[] = 'ALTER TABLE ' . $target;
            $lines[] = 'ADD CONSTRAINT ' . $constraintName;
            $lines[] = 'PRIMARY KEY (ROW_ID);';
            $lines[] = '';
        }

        return $lines;
    }

    protected function resolveDeterministicForeignKeyRelationships(AnonymousSiebelColumn $column): array
    {
        $relationships = [];

        $related = $column->related_columns;
        if (is_array($related) && $related !== []) {
            foreach ($related as $rel) {
                if (is_array($rel)) {
                    $relationships[] = $rel;
                }
            }
        }

        if ($relationships === []) {
            $raw = trim((string) ($column->related_columns_raw ?? ''));
            if ($raw !== '') {
                $relationships = $this->parseRelatedColumnsRaw($raw, $column);
            }
        }

        return $relationships;
    }

    protected function findTargetQualifiedForSourceTable(array $mappings, string $sourceTable): ?string
    {
        foreach ($mappings as $mapping) {
            if (($mapping['source_table'] ?? null) === $sourceTable) {
                return $mapping['target_qualified'] ?? null;
            }
        }

        return null;
    }

    protected function applyJobPlaceholdersToSql(string $sql, array $replacements): string
    {
        if ($replacements === []) {
            return $sql;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $sql);
    }
}
