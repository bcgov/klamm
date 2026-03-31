<?php

namespace App\Services\Anonymizer;

use App\Services\Anonymizer\Concerns\BuildsDoubleSeededDeterministicOracleScripts;
use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Models\Anonymizer\AnonymizationPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnonymizationJobScriptService
{
    use BuildsDoubleSeededDeterministicOracleScripts;

    private const WHERE_IN_CHUNK_SIZE = 10000;

    /**
     * Number of table IDs to include per WHERE IN chunk when loading columns
     * for inline select-list building, PK/FK generation, etc. Keeps peak memory
     * bounded for schema-wide (FULL) jobs that can span thousands of tables.
     */
    protected const TABLE_COLUMN_CHUNK_SIZE = 100;

    protected const SEED_PLACEHOLDERS = [
        '{{SEED_MAP_LOOKUP}}',
        '{{SEED_EXPR}}',
        '{{SEED_SOURCE_QUALIFIED}}',
    ];

    // Cache for resolved methods to avoid redundant lookups.
    // Key: column ID, Value: AnonymizationMethods|null
    // @var array<int, AnonymizationMethods|null>
    protected array $methodCache = [];

    // Cache table column-name lookups for seed fallback inference.
    // Key: table_id, Value: uppercase column-name map.
    protected array $tableColumnNameCache = [];

    // The strategy selected on the current job, used by resolveMethodForColumn
    // to pick the correct method from a rule's method assignments.
    protected ?string $currentJobStrategy = null;

    protected function oracleStringLiteral(?string $value): string
    {
        if ($value === null) {
            return "''";
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function oracleColumnTypeForColumn(AnonymousSiebelColumn $column): string
    {
        $typeName = strtolower(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));

        if ($typeName === '') {
            $typeName = strtolower(trim((string) ($column->data_type ?? '')));
        }

        if (str_contains($typeName, 'clob')) {
            return 'CLOB';
        }

        if (str_contains($typeName, 'date')) {
            return 'DATE';
        }

        if (str_contains($typeName, 'timestamp')) {
            return 'TIMESTAMP';
        }

        if (str_contains($typeName, 'number') || str_contains($typeName, 'numeric') || str_contains($typeName, 'decimal')) {
            $precision = (int) ($column->data_precision ?? 0);
            $scale = (int) ($column->data_scale ?? 0);

            if ($precision > 0) {
                return $scale > 0 ? "NUMBER({$precision},{$scale})" : "NUMBER({$precision})";
            }

            return 'NUMBER';
        }

        if (str_contains($typeName, 'char') || str_contains($typeName, 'varchar')) {
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            if ($length <= 0) {
                $length = 255;
            }

            $length = min($length, 4000);
            return "VARCHAR2({$length})";
        }

        return 'VARCHAR2(4000)';
    }

    protected function normalizeJobOption(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function normalizeRelationKind(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return $value === 'view' ? 'view' : 'table';
    }

    protected function shouldNullUnselectedColumns(?AnonymizationJobs $job): bool
    {
        return $job?->job_type === AnonymizationJobs::TYPE_PARTIAL;
    }

    protected function loadColumnsForIds(array $columnIds): \Illuminate\Database\Eloquent\Collection
    {
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));
        if ($columnIds === []) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return AnonymousSiebelColumn::query()
            ->with([
                'anonymizationMethods.packages',
                'anonymizationRule.methods.packages',
                'table.schema.database',
                'dataType',
                'parentColumns.table.schema.database',
            ])
            ->whereIn('id', $columnIds)
            ->get();
    }

    protected function methodUsesSeedPlaceholders(?AnonymizationMethods $method): bool
    {
        $sqlBlock = trim((string) ($method?->sql_block ?? ''));

        if ($sqlBlock === '') {
            return false;
        }

        foreach (self::SEED_PLACEHOLDERS as $placeholder) {
            if (str_contains($sqlBlock, $placeholder)) {
                return true;
            }
        }

        return false;
    }

    protected function oracleColumnMaxLength(AnonymousSiebelColumn $column): int
    {
        // Prefer the loaded dataType relation (same source used by oracleColumnTypeForColumn)
        // so that max-length expressions stay consistent with DDL column type declarations.
        // Fall back to the raw `data_type` field when the relation is not loaded.
        $typeName = strtolower(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));
        if ($typeName === '') {
            $typeName = strtolower(trim((string) ($column->data_type ?? '')));
        }

        if (str_contains($typeName, 'clob')) {
            return 4000;
        }

        if (
            str_contains($typeName, 'char')
            || str_contains($typeName, 'varchar')
            || str_contains($typeName, 'nvarchar')
            || str_contains($typeName, 'nchar')
        ) {
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            if ($length <= 0) {
                $length = 255;
            }

            return min($length, 4000);
        }

        return 4000;
    }

    protected function isClobColumn(AnonymousSiebelColumn $column): bool
    {
        $typeName = strtolower(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));

        if ($typeName === '') {
            $typeName = strtolower(trim((string) ($column->data_type ?? '')));
        }

        return $typeName !== '' && str_contains($typeName, 'clob');
    }

    protected function oracleNullExpressionForColumn(AnonymousSiebelColumn $column): string
    {
        if ($this->isClobColumn($column)) {
            return 'EMPTY_CLOB()';
        }

        return 'CAST(NULL AS ' . $this->oracleColumnTypeForColumn($column) . ')';
    }

    public function buildForJob(AnonymizationJobs $job): string
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
            'columns.anonymizationRule.methods.packages',
            'columns.table.schema.database',
            'columns.parentColumns.table.schema.database',
        ]);

        $columns = $job->columns ?? collect();

        if ($this->normalizeJobOption($job->seed_store_mode) === 'double-seeded') {
            $script = $this->buildDoubleSeededDeterministicFromColumns($columns, $job);
        } else {
            $script = $this->buildFromColumns($columns, $job);
        }

        if (trim($script) === '') {
            return '-- No anonymization SQL generated: no columns or anonymization methods configured for this job.';
        }

        return $script;
    }

    public function buildCloneOnlyForJob(AnonymizationJobs $job): string
    {
        $rewriteContext = $this->buildJobTableRewriteContext(collect(), $job);

        if ($rewriteContext === []) {
            // Provide diagnostic info about why context failed
            $targetSchema = $this->targetSchemaForJob($job);
            $tablePrefix = $this->tablePrefixForJob($job);

            if (! $targetSchema) {
                return '-- No SQL generated: could not determine target schema for job type "' . ($job->job_type ?? 'unknown') . '".';
            }

            if (! $tablePrefix) {
                return '-- No SQL generated: could not derive table prefix from job name "' . ($job->name ?? '') . '".';
            }

            return '-- No SQL generated: this job has no explicit columns and no scoped databases/schemas/tables selected.';
        }

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);

        if ($tableCloneStatements === []) {
            // Provide diagnostic info about why no tables were found
            $schemaIds = $this->schemaIdsForJobOrSelection($job, collect());
            $databaseCount = DB::table('anonymization_job_databases')->where('job_id', $job->getKey())->count();
            $schemaCount = DB::table('anonymization_job_schemas')->where('job_id', $job->getKey())->count();
            $tableCount = DB::table('anonymization_job_tables')->where('job_id', $job->getKey())->count();

            $scopeInfo = "databases={$databaseCount}, schemas={$schemaCount}, tables={$tableCount}";

            if ($schemaIds === []) {
                return "-- No SQL generated: no schema scope could be resolved. Job scope pivot counts: {$scopeInfo}. Ensure at least one database, schema, or table is selected.";
            }

            $tableCountInSchema = AnonymousSiebelTable::query()
                ->withTrashed()
                ->whereIn('schema_id', $schemaIds)
                ->count();

            return "-- No SQL generated: resolved " . count($schemaIds) . " schema(s) but found {$tableCountInSchema} table(s). Job scope pivot counts: {$scopeInfo}.";
        }

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);
        $lines = array_merge($lines, $this->buildDeterministicRandomSeedSection($rewriteContext));

        $lines[] = $this->commentDivider('=');
        $targetMode = $this->normalizeJobOption((string) ($rewriteContext['target_table_mode'] ?? '')) ?: 'prefixed';
        $modeLabel = $targetMode === 'anon'
            ? 'mode INITIAL_* → ANON_*'
            : ($targetMode === 'exact'
                ? 'exact source table names'
                : ('prefix ' . ($rewriteContext['table_prefix'] ?? 'none')));
        $lines[] = '-- Target Tables'
            . ' (schema ' . ($rewriteContext['target_schema'] ?? 'unknown') . ')'
            . ' (' . $modeLabel . ')';
        $lines[] = '-- Creates working copies and keeps all updates isolated.';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';
        $lines = array_merge($lines, $tableCloneStatements);
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $preMaskSql = trim((string) ($job->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Pre-mask SQL';
            $lines[] = '-- Runs after target tables/views are created, before seed maps are created.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $preMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $postMaskSql = trim((string) ($job->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Post-mask SQL';
            $lines[] = '-- Runs after seed maps are created.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $postMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines));
    }

    public function reviewSeedContractsForJob(AnonymizationJobs $job): array
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
            'columns.table.schema.database',
            'columns.parentColumns.table.schema.database',
        ]);

        $columns = $job->columns ?? collect();

        if ($columns->isEmpty()) {
            return [
                'errors' => collect(),
                'warnings' => collect(),
                'issues' => collect(),
            ];
        }

        if (method_exists($columns, 'loadMissing')) {
            $columns->loadMissing([
                'anonymizationMethods.packages',
                'anonymizationRule.methods.packages',
                'table.schema.database',
                'parentColumns.table.schema.database',
            ]);
        }

        $ordered = $this->topologicallySortColumns($columns);

        return $this->validateSeedContracts($ordered);
    }

    public function buildForColumnIds(array $columnIds, ?AnonymizationJobs $job = null): string
    {
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));

        if ($columnIds === []) {
            return '';
        }

        // For very large column sets, we need to be memory-conscious.
        // Load columns in chunks but with minimal initial eager loading.
        // Deep relations will be loaded on-demand or in a second pass.
        $columns = collect();

        // Use smaller chunks for large datasets to reduce peak memory
        $chunkSize = count($columnIds) > 5000 ? 500 : self::WHERE_IN_CHUNK_SIZE;

        // Select only fields used by downstream SQL generation.
        $columnSelect = [
            'id',
            'column_name',
            'table_id',
            'data_type_id',
            'data_length',
            'data_precision',
            'data_scale',
            'char_length',
            'seed_contract_mode',
            'seed_contract_expression',
            'anonymization_required',
        ];

        foreach (array_chunk($columnIds, $chunkSize) as $chunk) {
            $batch = AnonymousSiebelColumn::query()
                ->select($columnSelect)
                ->with([
                    'anonymizationMethods',
                    'anonymizationRule.methods',
                    'table.schema.database',
                    'dataType',
                ])
                ->whereIn('id', $chunk)
                ->get();

            foreach ($batch as $item) {
                $columns->push($item);
            }

            unset($batch);
            gc_collect_cycles();
        }

        // Load parentColumns separately with only the fields needed
        $columns = AnonymousSiebelColumn::hydrate($columns->all());
        $columns->load(['parentColumns' => function ($q) {
            $q->select(['anonymous_siebel_columns.id', 'column_name', 'table_id', 'seed_contract_mode']);
        }]);

        // Load packages only for methods that are actually used
        $methodsWithPackages = $columns
            ->pluck('anonymizationMethods')
            ->flatten()
            ->filter()
            ->unique('id');

        if ($methodsWithPackages->isNotEmpty()) {
            // Convert to Eloquent Collection to enable lazy loading
            $methodsWithPackages = AnonymizationMethods::hydrate($methodsWithPackages->values()->all());
            $methodsWithPackages->load('packages');
        }

        return $this->buildFromColumns($columns, $job);
    }

    // Build SQL for a specific chunk of column IDs.
    // Used by GenerateAnonymizationJobSqlChunk to process large jobs in parallel.
    // @param array $columnIds Array of column IDs to process
    // @param AnonymizationJobs $job The parent anonymization job
    // @param int $chunkIndex Index of this chunk (for logging/ordering)
    // @return string Generated SQL for this chunk
    public function buildForColumnIdsChunk(array $columnIds, AnonymizationJobs $job, int $chunkIndex): string
    {
        if (empty($columnIds)) {
            return '';
        }

        $columns = AnonymousSiebelColumn::with([
            'anonymizationMethods',
            'anonymizationRule.methods',
            'table.schema.database'
        ])
            ->whereIn('id', $columnIds)
            ->where('must_be_anonymized', true)
            ->get();

        if ($columns->isEmpty()) {
            return '';
        }

        // Group columns by table for organized SQL generation
        $columnsByTable = $columns->groupBy('anonymous_siebel_table_id');

        $sqlParts = [];
        $sqlParts[] = "-- Chunk {$chunkIndex}: Processing " . count($columns) . " columns from " . $columnsByTable->count() . " tables";
        $sqlParts[] = '';

        foreach ($columnsByTable as $tableId => $tableColumns) {
            $firstColumn = $tableColumns->first();
            $table = $firstColumn->table;
            $schema = $table->schema;
            $database = $schema->database;

            $fullTableName = "{$database->name}.{$schema->name}.{$table->name}";

            $sqlParts[] = "-- Table: {$fullTableName}";

            foreach ($tableColumns as $column) {
                if ($column->anonymizationMethods->isNotEmpty()) {
                    $method = $column->anonymizationMethods->first();

                    $sqlParts[] = "-- Column: {$column->name} | Method: {$method->name}";

                    if ($method->sql_block) {
                        // Replace placeholders in the SQL block
                        $sqlBlock = str_replace(
                            ['{table_name}', '{column_name}', '{schema_name}', '{database_name}'],
                            [$table->name, $column->name, $schema->name, $database->name],
                            $method->sql_block
                        );
                        $sqlParts[] = $sqlBlock;
                    }

                    $sqlParts[] = '';
                }
            }

            $sqlParts[] = '';
        }

        return implode("\n", $sqlParts);
    }

    // Optimized version of buildForColumnIds for very large column sets (2000+).
    // Key optimizations:
    // 1. Pre-loads all method IDs and caches them to avoid repeated lookups
    // 2. Uses raw queries for relationship data where possible
    // 3. Processes columns in smaller batches with explicit memory cleanup
    // 4. Logs progress throughout for monitoring
    public function buildForColumnIdsOptimized(array $columnIds, ?AnonymizationJobs $job = null): string
    {
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));

        if ($columnIds === []) {
            return '';
        }

        $totalColumns = count($columnIds);
        Log::info('AnonymizationJobScriptService: starting optimized build', [
            'total_columns' => $totalColumns,
            'job_id' => $job?->id,
        ]);

        // Step 1: Pre-cache all column->method mappings using a single query
        $this->methodCache = [];
        $this->currentJobStrategy = $job?->strategy;
        $methodMappings = DB::table('anonymization_method_column')
            ->whereIn('column_id', $columnIds)
            ->select('column_id', 'method_id')
            ->get()
            ->groupBy('column_id');

        $jobPivotMap = [];
        if ($job?->id) {
            $jobPivotRows = DB::table('anonymization_job_columns')
                ->where('job_id', $job->id)
                ->whereIn('column_id', $columnIds)
                ->select(['column_id', 'anonymization_method_id'])
                ->get();

            foreach ($jobPivotRows as $row) {
                $colId = (int) ($row->column_id ?? 0);
                $methodId = (int) ($row->anonymization_method_id ?? 0);
                if ($colId > 0 && $methodId > 0) {
                    $jobPivotMap[$colId] = $methodId;
                }
            }
        }

        // Pre-load all methods we'll need
        $allMethodIds = $methodMappings->flatten()->pluck('method_id')->unique()->all();
        $allMethods = AnonymizationMethods::withTrashed()
            ->with('packages')
            ->whereIn('id', $allMethodIds)
            ->get()
            ->keyBy('id');

        Log::info('AnonymizationJobScriptService: pre-loaded methods', [
            'method_count' => $allMethods->count(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // Step 2: Load columns in optimized batches
        $columns = collect();
        $batchSize = 500;
        $processed = 0;

        // Select only fields used by downstream SQL generation.
        $columnSelect = [
            'id',
            'column_name',
            'table_id',
            'data_type_id',
            'data_length',
            'data_precision',
            'data_scale',
            'char_length',
            'seed_contract_mode',
            'seed_contract_expression',
            'anonymization_required',
        ];

        foreach (array_chunk($columnIds, $batchSize) as $chunkIndex => $chunk) {
            $batch = AnonymousSiebelColumn::query()
                ->select($columnSelect)
                ->with([
                    'table.schema.database',
                    'dataType',
                    'parentColumns' => function ($q) {
                        $q->select(['anonymous_siebel_columns.id', 'column_name', 'table_id', 'seed_contract_mode']);
                    },
                    'anonymizationRule.methods',
                ])
                ->whereIn('id', $chunk)
                ->get();

            // Manually attach methods from our pre-loaded cache
            foreach ($batch as $column) {
                $columnMethodIds = $methodMappings->get($column->id)?->pluck('method_id')->all() ?? [];
                $columnMethods = collect($columnMethodIds)
                    ->map(fn($id) => $allMethods->get($id))
                    ->filter();

                // Set the relation without triggering a query
                $column->setRelation('anonymizationMethods', $columnMethods);

                if ($jobPivotMap !== [] && isset($jobPivotMap[$column->id])) {
                    $column->setRelation('pivot', (object) [
                        'anonymization_method_id' => $jobPivotMap[$column->id],
                    ]);
                }

                // Pre-populate method cache
                if ($columnMethods->isNotEmpty()) {
                    $this->methodCache[$column->id] = $columnMethods->first();
                }
            }

            $columns = $columns->merge($batch);
            $processed += count($chunk);

            if ($chunkIndex % 5 === 0) {
                Log::info('AnonymizationJobScriptService: loading columns', [
                    'processed' => $processed,
                    'total' => $totalColumns,
                    'percent' => round(($processed / $totalColumns) * 100, 1),
                    'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);
                gc_collect_cycles();
            }

            unset($batch);
        }

        Log::info('AnonymizationJobScriptService: all columns loaded, starting SQL generation', [
            'column_count' => $columns->count(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // Step 3: Build SQL with cached methods
        $result = $this->buildFromColumns($columns, $job);

        Log::info('AnonymizationJobScriptService: optimized build complete', [
            'sql_length' => strlen($result),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        return $result;
    }

    /**
     * Prepare shared context for chunked SQL generation.
     *
     * Uses an entirely raw-query approach so that zero Eloquent column models
     * are accumulated in memory.  Only the ~30 method models (with packages)
     * and a handful of seed-provider column models are hydrated.
     */
    public function prepareChunkedContextForColumnIds(array $columnIds, ?AnonymizationJobs $job = null): array
    {
        $this->currentJobStrategy = $job?->strategy;
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));

        Log::info('AnonymizationJobScriptService: preparing chunked context (lightweight)', [
            'job_id' => $job?->id,
            'column_count' => count($columnIds),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $emptyResult = [
            'ordered_table_ids' => [],
            'selected_column_ids' => [],
            'prefix_sql' => '',
            'suffix_sql' => '',
            'seed_provider_map' => [],
            'rewrite_context' => [],
            'seed_map_context' => [],
            'halted' => false,
        ];

        if ($columnIds === []) {
            return $emptyResult;
        }

        // ── 1. Table ordering via raw queries (zero Eloquent) ──────────
        $tableIdsByColumn = [];  // column_id → table_id
        $tableEdges = [];        // parentTableId → [childTableId => true, ...]
        $allTableIds = [];       // table_id => true

        foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
            $rows = DB::table('anonymous_siebel_columns')
                ->whereIn('id', $chunk)
                ->select(['id', 'table_id'])
                ->get();
            foreach ($rows as $r) {
                $tableIdsByColumn[(int) $r->id] = (int) $r->table_id;
                $allTableIds[(int) $r->table_id] = true;
            }
        }

        // Cross-table dependency edges (parent → child).
        foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
            $deps = DB::table('anonymous_siebel_column_dependencies as dep')
                ->join('anonymous_siebel_columns as child', 'child.id', '=', 'dep.child_field_id')
                ->join('anonymous_siebel_columns as parent', 'parent.id', '=', 'dep.parent_field_id')
                ->whereIn('dep.child_field_id', $chunk)
                ->select([
                    'child.table_id as child_table_id',
                    'parent.table_id as parent_table_id',
                ])
                ->get();
            foreach ($deps as $d) {
                $ct = (int) $d->child_table_id;
                $pt = (int) $d->parent_table_id;
                $allTableIds[$ct] = true;
                $allTableIds[$pt] = true;
                if ($ct !== $pt && $pt > 0 && $ct > 0) {
                    $tableEdges[$pt][$ct] = true;
                }
            }
        }

        // Kahn's topological sort on table IDs.
        $sortedTableIds = array_keys($allTableIds);
        sort($sortedTableIds);
        $inDeg = array_fill_keys($sortedTableIds, 0);
        foreach ($tableEdges as $from => $targets) {
            foreach (array_keys($targets) as $to) {
                if (! isset($inDeg[$to])) {
                    $inDeg[$to] = 0;
                }
                $inDeg[$to]++;
            }
        }
        $queue = [];
        foreach ($inDeg as $id => $c) {
            if ($c === 0) {
                $queue[] = $id;
            }
        }
        $orderedTableIds = [];
        while ($queue !== []) {
            $cur = array_shift($queue);
            $orderedTableIds[] = $cur;
            foreach (array_keys($tableEdges[$cur] ?? []) as $to) {
                $inDeg[$to]--;
                if ($inDeg[$to] === 0) {
                    $queue[] = $to;
                }
            }
        }
        // Append any cycles / unreachable nodes.
        if (count($orderedTableIds) < count($sortedTableIds)) {
            $remaining = array_diff($sortedTableIds, $orderedTableIds);
            sort($remaining);
            $orderedTableIds = array_merge($orderedTableIds, $remaining);
        }

        if ($orderedTableIds === []) {
            return $emptyResult;
        }

        unset($tableEdges, $inDeg, $sortedTableIds);
        gc_collect_cycles();

        Log::info('AnonymizationJobScriptService: lightweight context tables ordered', [
            'job_id' => $job?->id,
            'table_count' => count($orderedTableIds),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // ── 2. Rewrite context (table → schema → database mapping) ────
        // Load table models (with schema.database) for the rewrite context
        // builder. There are far fewer tables than columns (~3-5 K vs 258 K).
        $tableModels = AnonymousSiebelTable::withTrashed()
            ->with(['schema.database'])
            ->whereIn('id', array_keys($allTableIds))
            ->get()
            ->keyBy(fn($t) => (int) $t->getKey());

        // Build a minimal stub collection that satisfies
        // buildJobTableRewriteContext's signature (Collection of columns with
        // a ->table relation).  One stub per unique table is sufficient.
        $stubColumns = collect();
        foreach ($tableModels as $tid => $tableModel) {
            $stub = new AnonymousSiebelColumn();
            $stub->id = 0;
            $stub->table_id = $tid;
            $stub->setRelation('table', $tableModel);
            $stub->setRelation('parentColumns', collect());
            $stubColumns->push($stub);
        }

        $rewriteContext = $this->buildJobTableRewriteContext($stubColumns, $job, true);
        $rewriteContext['masking_mode'] = 'inline';
        $rewriteContext['source_alias'] = 'src';
        unset($stubColumns, $tableModels);
        gc_collect_cycles();

        Log::info('AnonymizationJobScriptService: lightweight context rewrite ready', [
            'job_id' => $job?->id,
            'tables_in_rewrite' => count($rewriteContext['tables_by_id'] ?? []),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // ── 3. Method resolution & seed provider map ──────────────────
        // Pre-resolve column → method_id from the job pivot (raw query).
        $columnMethodMap = [];   // column_id => method_id
        if ($job) {
            foreach (array_chunk($columnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
                $rows = DB::table('anonymization_job_columns')
                    ->where('job_id', $job->id)
                    ->whereIn('column_id', $chunk)
                    ->whereNotNull('anonymization_method_id')
                    ->select(['column_id', 'anonymization_method_id'])
                    ->get();
                foreach ($rows as $r) {
                    $columnMethodMap[(int) $r->column_id] = (int) $r->anonymization_method_id;
                }
            }
        }

        // Load all method models (small — typically < 50).
        $allMethodIds = array_values(array_unique(array_values($columnMethodMap)));
        $allMethods = $allMethodIds !== []
            ? AnonymizationMethods::withTrashed()->with('packages')->whereIn('id', $allMethodIds)->get()->keyBy('id')
            : collect();

        // Identify seed providers / consumers.
        $emitsSeedMethodIds = $allMethods->filter(fn($m) => $m->emits_seed)->pluck('id')->flip()->all();
        $requiresSeedMethodIds = $allMethods->filter(fn($m) => $m->requires_seed)->pluck('id')->flip()->all();

        $providerColumnIds = [];
        $consumerColumnIds = [];
        foreach ($columnMethodMap as $cid => $mid) {
            if (isset($emitsSeedMethodIds[$mid])) {
                $providerColumnIds[] = $cid;
            }
            if (isset($requiresSeedMethodIds[$mid])) {
                $consumerColumnIds[] = $cid;
            }
        }

        $providersByTable = [];
        foreach ($providerColumnIds as $pid) {
            $tid = $tableIdsByColumn[$pid] ?? 0;
            if ($tid > 0) {
                $providersByTable[$tid][] = $pid;
            }
        }

        // Hydrate only the seed-provider columns (tiny subset).
        $providerModels = collect();
        if ($providerColumnIds !== []) {
            $providerModels = AnonymousSiebelColumn::query()
                ->select([
                    'id',
                    'column_name',
                    'table_id',
                    'data_type_id',
                    'data_length',
                    'data_precision',
                    'data_scale',
                    'char_length',
                    'seed_contract_mode',
                    'seed_contract_expression'
                ])
                ->with(['dataType'])
                ->whereIn('id', $providerColumnIds)
                ->get()
                ->keyBy('id');
        }

        $seedProviderMap = [];
        foreach ($providerColumnIds as $pid) {
            $provider = $providerModels->get($pid);
            $seedProviderMap[$pid] = [
                'provider_id' => $pid,
                'expression' => $provider ? $this->seedExpressionForProvider($provider) : 'tgt.UNKNOWN',
            ];
        }

        $consumerFallbackExpr = [];
        $parentProviderByChild = [];
        if ($consumerColumnIds !== []) {
            $selectedColumnIdSet = array_fill_keys($columnIds, true);
            foreach (array_chunk($consumerColumnIds, self::WHERE_IN_CHUNK_SIZE) as $chunk) {
                $rows = DB::table('anonymous_siebel_column_dependencies as dep')
                    ->join('anonymous_siebel_columns as child', 'child.id', '=', 'dep.child_field_id')
                    ->join('anonymous_siebel_columns as parent', 'parent.id', '=', 'dep.parent_field_id')
                    ->whereIn('dep.child_field_id', $chunk)
                    ->select([
                        'child.id as child_id',
                        'child.column_name as child_column_name',
                        'parent.id as parent_id',
                        'parent.column_name as parent_column_name',
                    ])
                    ->get();

                foreach ($rows as $row) {
                    $childId = (int) ($row->child_id ?? 0);
                    $childColumnName = trim((string) ($row->child_column_name ?? ''));
                    if ($childId <= 0 || $childColumnName === '') {
                        continue;
                    }

                    $parentColumnName = strtoupper(trim((string) ($row->parent_column_name ?? '')));
                    $parentId = (int) ($row->parent_id ?? 0);
                    $isRowIdParent = $parentColumnName === 'ROW_ID';

                    if ($isRowIdParent) {
                        $consumerFallbackExpr[$childId] = 'tgt.' . $childColumnName;
                    }

                    if ($parentId <= 0 || ! isset($selectedColumnIdSet[$parentId])) {
                        continue;
                    }

                    $existing = $parentProviderByChild[$childId] ?? null;
                    if (! $existing || ($isRowIdParent && ! ($existing['is_row_id'] ?? false))) {
                        $parentProviderByChild[$childId] = [
                            'provider_id' => $parentId,
                            'is_row_id' => $isRowIdParent,
                        ];
                    }
                }
            }

            // FK metadata fallback: for any consumer column that still has no explicit parent
            // dependency, infer the parent ROW_ID provider from related_columns / related_columns_raw.
            // This mirrors inferProviderFromRelatedColumnMeta used in the non-chunked path.
            $unresolvedConsumers = array_values(array_diff($consumerColumnIds, array_keys($parentProviderByChild)));
            if ($unresolvedConsumers !== [] && $providersByTable !== []) {
                // Build UPPER(SCHEMA|TABLE) → table_id from the rewrite context.
                $tablesBySchemaTableUpper = [];
                foreach (($rewriteContext['tables_by_id'] ?? []) as $mapTid => $mapEntry) {
                    $mapS = strtoupper(trim((string) ($mapEntry['source_schema'] ?? '')));
                    $mapT = strtoupper(trim((string) ($mapEntry['source_table'] ?? '')));
                    if ($mapS !== '' && $mapT !== '') {
                        $tablesBySchemaTableUpper[$mapS . '|' . $mapT] = (int) $mapTid;
                    }
                }

                foreach (array_chunk($unresolvedConsumers, self::WHERE_IN_CHUNK_SIZE) as $fkChunk) {
                    $fkRows = DB::table('anonymous_siebel_columns as c')
                        ->join('anonymous_siebel_tables as t', 't.id', '=', 'c.table_id')
                        ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
                        ->whereIn('c.id', $fkChunk)
                        ->select(['c.id', 'c.related_columns_raw', 'c.related_columns', 's.schema_name'])
                        ->get();

                    foreach ($fkRows as $fkRow) {
                        $cid        = (int) ($fkRow->id ?? 0);
                        $schemaName = strtoupper(trim((string) ($fkRow->schema_name ?? '')));
                        if ($cid <= 0) {
                            continue;
                        }

                        // Parse FK relationships: prefer JSON, fall back to raw string.
                        $related = [];
                        if ($fkRow->related_columns && trim($fkRow->related_columns) !== '') {
                            $decoded = json_decode($fkRow->related_columns, true);
                            if (is_array($decoded)) {
                                $related = $decoded;
                            }
                        }
                        if ($related === [] && $fkRow->related_columns_raw && trim($fkRow->related_columns_raw) !== '') {
                            foreach (preg_split('/\s*;\s*/', trim($fkRow->related_columns_raw)) ?: [] as $rawPart) {
                                $rawPart = trim((string) $rawPart);
                                if ($rawPart === '') {
                                    continue;
                                }
                                if (preg_match('/^([^.\s]+)\.([^.\s]+)\.([^\s]+)(?:\s+via\s+\S+)?$/i', $rawPart, $rm)) {
                                    $related[] = ['direction' => 'OUTBOUND', 'schema' => $rm[1], 'table' => $rm[2], 'column' => trim($rm[3], ',')];
                                } else {
                                    $related[] = ['direction' => 'OUTBOUND', 'schema' => $schemaName, 'table' => $rawPart, 'column' => 'ROW_ID'];
                                }
                            }
                        }

                        foreach ($related as $rel) {
                            if (strtoupper((string) ($rel['direction'] ?? 'OUTBOUND')) !== 'OUTBOUND') {
                                continue;
                            }
                            $pSchema = strtoupper(trim((string) ($rel['schema'] ?? '')));
                            $pTable  = strtoupper(trim((string) ($rel['table'] ?? '')));
                            $pCol    = strtoupper(trim((string) ($rel['column'] ?? 'ROW_ID')));
                            if ($pSchema === '' || $pTable === '' || $pCol !== 'ROW_ID') {
                                continue;
                            }
                            $parentTableId    = $tablesBySchemaTableUpper[$pSchema . '|' . $pTable] ?? null;
                            $tableProviderList = $parentTableId ? ($providersByTable[$parentTableId] ?? []) : [];
                            if ($tableProviderList === []) {
                                continue;
                            }
                            $parentProviderId = $tableProviderList[0];
                            if (! isset($selectedColumnIdSet[$parentProviderId])) {
                                continue;
                            }
                            $parentProviderByChild[$cid] = [
                                'provider_id' => $parentProviderId,
                                'is_row_id'   => true,
                            ];
                            break; // take the first matching FK relationship
                        }
                    }
                }
            }

            $additionalProviderIds = [];
            foreach ($parentProviderByChild as $candidate) {
                $candidateProviderId = (int) ($candidate['provider_id'] ?? 0);
                if ($candidateProviderId > 0 && ! $providerModels->has($candidateProviderId)) {
                    $additionalProviderIds[] = $candidateProviderId;
                }
            }

            $additionalProviderIds = array_values(array_unique(array_filter(array_map('intval', $additionalProviderIds))));
            if ($additionalProviderIds !== []) {
                $additionalProviders = AnonymousSiebelColumn::query()
                    ->select([
                        'id',
                        'column_name',
                        'table_id',
                        'data_type_id',
                        'data_length',
                        'data_precision',
                        'data_scale',
                        'char_length',
                        'seed_contract_mode',
                        'seed_contract_expression'
                    ])
                    ->with(['dataType'])
                    ->whereIn('id', $additionalProviderIds)
                    ->get();

                foreach ($additionalProviders as $additionalProvider) {
                    $providerModels->put((int) $additionalProvider->id, $additionalProvider);
                }
            }
        }

        foreach ($consumerColumnIds as $cid) {
            if (isset($seedProviderMap[$cid])) {
                continue; // already a provider itself
            }
            $tid = $tableIdsByColumn[$cid] ?? 0;
            $tableProviders = $providersByTable[$tid] ?? [];
            $preferredProviderId = (int) (($parentProviderByChild[$cid]['provider_id'] ?? 0));
            $providerId = $preferredProviderId > 0
                ? $preferredProviderId
                : ($tableProviders !== [] ? $tableProviders[0] : 0);
            $provider = $providerModels->get($providerId);
            $seedProviderMap[$cid] = [
                'provider_id' => $providerId,
                'expression' => $provider
                    ? $this->seedExpressionForProvider($provider)
                    : ($consumerFallbackExpr[$cid] ?? 'tgt.ROW_ID'),
            ];
        }

        Log::info('AnonymizationJobScriptService: lightweight context seeds resolved', [
            'job_id' => $job?->id,
            'method_count' => $allMethods->count(),
            'provider_count' => count($providerColumnIds),
            'consumer_count' => count($consumerColumnIds),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        // ── 4. Seed map context ───────────────────────────────────────
        $needsSeedMap = false;
        foreach ($allMethods as $method) {
            if ($method->sql_block && (str_contains($method->sql_block, '{{SEED_MAP_LOOKUP}}') || str_contains($method->sql_block, '{{SEED_MAP_TABLE}}'))) {
                $needsSeedMap = true;
                break;
            }
        }

        $seedMapContext = [];
        if ($needsSeedMap && $providerModels->isNotEmpty()) {
            $targetSchema = $rewriteContext['target_schema'] ?? null;
            $prefix = $rewriteContext['table_prefix'] ?? null;
            $tableMap = $rewriteContext['tables_by_id'] ?? [];
            $seedStoreMode = strtolower(trim((string) ($job?->seed_store_mode ?? ($rewriteContext['seed_store_mode'] ?? 'temporary'))));
            $seedStoreSchema = trim((string) ($job?->seed_store_schema ?? ($rewriteContext['seed_store_schema'] ?? '')));
            $seedStorePrefix = trim((string) ($job?->seed_store_prefix ?? ($rewriteContext['seed_store_prefix'] ?? '')));
            $isPersistent = $seedStoreMode === 'persistent';
            if ($seedStoreSchema === '') {
                $seedStoreSchema = (string) $targetSchema;
            }
            if ($seedStorePrefix === '') {
                $seedStorePrefix = (string) $prefix;
            }

            $seedMapProviders = [];
            $seedMapProviderIds = [];
            foreach ($seedProviderMap as $providerEntry) {
                $entryProviderId = (int) ($providerEntry['provider_id'] ?? 0);
                if ($entryProviderId > 0) {
                    $seedMapProviderIds[$entryProviderId] = true;
                }
            }

            foreach (array_keys($seedMapProviderIds) as $seedMapProviderId) {
                $provider = $providerModels->get($seedMapProviderId);
                if (! $provider) {
                    continue;
                }

                $tableId = (int) ($provider->table_id ?? 0);
                $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;
                if (! is_array($mapped) || ! isset($mapped['target_qualified'], $mapped['source_table'])) {
                    continue;
                }
                $seedMapName = $this->oracleIdentifier(
                    ($isPersistent ? $seedStorePrefix : $prefix)
                        . '_SEEDMAP_' . ($mapped['source_table'] ?? 'T') . '_' . ($provider->column_name ?? 'C')
                );
                $columnType = $this->oracleColumnTypeForColumn($provider);

                // Compute the actual anonymized expression from the provider's method.
                // This ensures the seed map stores old_value → anonymized_value,
                // not an identity mapping (old_value → old_value) which breaks FK lookups.
                $providerMethodId = $columnMethodMap[(int) $provider->id] ?? 0;
                $providerMethod = $providerMethodId > 0 ? $allMethods->get($providerMethodId) : null;
                $anonymizedExpr = $this->anonymizedExpressionForSeedMap(
                    $provider,
                    $providerMethod,
                    $rewriteContext,
                    'src'
                );

                if ($anonymizedExpr !== null) {
                    $seedExpr = $anonymizedExpr;
                } else {
                    // Fallback to raw column reference when expression can't be extracted.
                    $seedExpr = $this->seedExpressionForProvider($provider);
                    $seedExpr = $this->renderSeedExpressionPlaceholders($seedExpr, $rewriteContext);
                    $seedExpr = str_replace('tgt.', 'src.', $seedExpr);
                }

                $seedMapProviders[(int) $provider->id] = [
                    'provider_id' => (int) $provider->id,
                    'provider_column' => $provider->column_name,
                    'provider_table' => $mapped['source_qualified'] ?? $mapped['target_qualified'],
                    'seed_expression' => $seedExpr,
                    'seed_map_table' => ($isPersistent ? $seedStoreSchema : $targetSchema) . '.' . $seedMapName,
                    'seed_map_persistence' => $isPersistent ? 'persistent' : 'temporary',
                    'old_value_type' => $columnType,
                    'new_value_type' => $columnType,
                    'source_alias' => 'src',
                ];
            }

            if ($seedMapProviders !== []) {
                $seedMapContext = ['providers' => $seedMapProviders];
            }
        }

        unset($providerModels, $tableIdsByColumn, $allTableIds, $columnMethodMap);
        gc_collect_cycles();

        // ── 5. Prefix SQL ─────────────────────────────────────────────
        $prefixLines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);
        $prefixLines = array_merge($prefixLines, $this->buildSourceAccessPreflightForClones($rewriteContext));
        $prefixLines = array_merge($prefixLines, $this->buildDeterministicRandomSeedSection($rewriteContext));

        // Contract validation is deferred to chunk workers for very large jobs
        // to avoid materializing all 258 K+ columns in the parent process.
        $prefixLines[] = $this->commentDivider('-');
        $prefixLines[] = '-- Note: Seed contract validation deferred to per-table chunk processing.';
        $prefixLines[] = $this->commentDivider('-');
        $prefixLines[] = '';

        $requiredPackageRefs = [];
        foreach ($allMethods as $methodModel) {
            $requiredPackageRefs = $this->collectPackageRefsFromSqlBlock(
                (string) ($methodModel->sql_block ?? ''),
                $requiredPackageRefs
            );
        }

        if ($requiredPackageRefs !== []) {
            $rewriteContext['required_package_refs'] = $requiredPackageRefs;
            $prefixLines = array_merge($prefixLines, $this->buildConditionalPackageBootstrap($rewriteContext));
            $prefixLines = array_merge($prefixLines, $this->buildRequiredPackagePreflight($rewriteContext));
        }

        // Seed maps must exist before chunk CTAS statements run, because
        // inline SELECT expressions can reference them during table creation.
        $seedMapStatements = $this->renderSeedMapTables($seedMapContext);
        if ($seedMapStatements !== []) {
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '-- Seed Maps (relationship preservation)';
            $prefixLines[] = '-- Lookup tables keep dependent keys aligned with seed providers.';
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';
            $prefixLines = array_merge($prefixLines, $seedMapStatements);
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';
        }

        // ── 6. Suffix SQL ─────────────────────────────────────────────
        $suffixLines = [];
        $preMaskSql = trim((string) ($job?->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '-- Pre-mask SQL';
            $suffixLines[] = '-- Runs after target tables/views are created, before seed maps are created.';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
            $suffixLines = array_merge($suffixLines, preg_split('/\R/', $preMaskSql) ?: []);
            $suffixLines[] = '';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
        }

        $postMaskSql = trim((string) ($job?->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '-- Post-mask SQL';
            $suffixLines[] = '-- Runs after seed maps are created.';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
            $suffixLines = array_merge($suffixLines, preg_split('/\R/', $postMaskSql) ?: []);
            $suffixLines[] = '';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
        }

        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '-- Constraints Skipped';
        $suffixLines[] = '-- PK/FK generation is skipped for large batch jobs to avoid memory spikes.';
        $suffixLines[] = '-- Re-run with a smaller scope if constraints are required.';
        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '';

        $hygiene = $this->renderSeedMapHygieneSection($seedMapContext, $job);
        if ($hygiene !== []) {
            $suffixLines = array_merge($suffixLines, $hygiene);
        }

        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '-- Finalize';
        $suffixLines[] = 'COMMIT;';
        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '';

        Log::info('AnonymizationJobScriptService: lightweight chunked context prepared', [
            'job_id' => $job?->id,
            'table_count' => count($orderedTableIds),
            'column_count' => count($columnIds),
            'prefix_length' => strlen(trim(implode(PHP_EOL, $prefixLines))),
            'suffix_length' => strlen(trim(implode(PHP_EOL, $suffixLines))),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        return [
            'ordered_table_ids' => $orderedTableIds,
            'selected_column_ids' => $columnIds,
            'prefix_sql' => trim(implode(PHP_EOL, $prefixLines)),
            'suffix_sql' => trim(implode(PHP_EOL, $suffixLines)),
            'seed_provider_map' => $seedProviderMap,
            'rewrite_context' => $rewriteContext,
            'seed_map_context' => $seedMapContext,
            'halted' => false,
        ];
    }

    public function buildMaskingChunk(
        array $orderedIds,
        array $seedProviderMap,
        array $rewriteContext,
        array $seedMapContext,
        array $allOrderedIds,
        ?AnonymizationJobs $job = null
    ): string {
        $this->currentJobStrategy = $job?->strategy;
        $orderedIds = array_values(array_unique(array_filter(array_map('intval', $orderedIds))));
        if ($orderedIds === []) {
            return '';
        }

        $columns = AnonymousSiebelColumn::query()
            ->with([
                'anonymizationMethods',
                'anonymizationRule.methods',
                'table.schema.database',
                'dataType',
                'parentColumns',
            ])
            ->whereIn('id', $orderedIds)
            ->get()
            ->keyBy('id');

        if ($columns->isEmpty()) {
            return '';
        }

        if ($job?->id) {
            $jobPivotRows = DB::table('anonymization_job_columns')
                ->where('job_id', $job->id)
                ->whereIn('column_id', $orderedIds)
                ->select(['column_id', 'anonymization_method_id'])
                ->get();

            foreach ($jobPivotRows as $row) {
                $colId = (int) ($row->column_id ?? 0);
                if (! $columns->has($colId)) {
                    continue;
                }
                $columns->get($colId)?->setRelation('pivot', (object) [
                    'anonymization_method_id' => (int) ($row->anonymization_method_id ?? 0),
                ]);
            }
        }

        // Build a unique provider list so we can fetch provider metadata once.
        $providerIds = [];
        foreach ($orderedIds as $columnId) {
            $providerId = (int) ($seedProviderMap[$columnId]['provider_id'] ?? 0);
            if ($providerId > 0) {
                $providerIds[$providerId] = true;
            }
        }

        $providers = [];
        if ($providerIds !== []) {
            $providers = AnonymousSiebelColumn::query()
                ->with(['table.schema.database', 'dataType'])
                ->whereIn('id', array_keys($providerIds))
                ->get()
                ->keyBy('id');
        }

        $lines = [];
        $lastMethodId = null;
        $orderedLookup = array_values(array_unique(array_filter(array_map('intval', $allOrderedIds))));

        foreach ($orderedIds as $columnId) {
            $column = $columns->get($columnId);
            if (! $column) {
                continue;
            }

            $method = $this->resolveMethodForColumn($column);
            $methodId = $method?->id ?? 'none';

            if ($methodId !== $lastMethodId) {
                $lines[] = $this->commentDivider('-');
                $lines[] = $this->methodHeading($method);
                $lastMethodId = $methodId;
            }

            $sqlBlock = trim((string) ($method?->sql_block ?? ''));

            $dependencies = $this->dependencyNames($column, $orderedLookup);
            $depNote = $dependencies !== []
                ? ' (depends on: ' . implode(', ', $dependencies) . ')'
                : '';

            $lines[] = '-- Column: ' . $this->describeColumn($column) . $depNote;

            if ($sqlBlock === '') {
                $lines[] = '-- No SQL block defined for this method.';
            } else {
                $providerId = (int) ($seedProviderMap[$columnId]['provider_id'] ?? 0);
                $providerModel = $providerId > 0 ? $providers->get($providerId) : null;

                $seedProviders = [
                    $columnId => [
                        'provider' => $providerModel,
                        'expression' => $seedProviderMap[$columnId]['expression'] ?? null,
                    ],
                ];

                foreach ($this->renderSqlBlocksForColumns($sqlBlock, collect([$column]), $seedProviders, $rewriteContext, $seedMapContext) as $renderedBlock) {
                    $lines[] = $renderedBlock;
                }
            }

            $lines[] = '';
        }

        return trim(implode(PHP_EOL, $lines));
    }

    public function buildInlineTableChunk(
        array $tableIds,
        array $rewriteContext,
        array $seedProviderMap,
        array $seedMapContext,
        array $selectedColumnIds,
        ?AnonymizationJobs $job = null
    ): string {
        $this->currentJobStrategy = $job?->strategy;
        $tableIds = array_values(array_unique(array_filter(array_map('intval', $tableIds))));
        if ($tableIds === []) {
            return '';
        }
        $rewriteContext['skip_common_preflight'] = true;
        $sourceAlias = (string) ($rewriteContext['source_alias'] ?? 'src');
        $warnings = $this->applyInlineMaskedSelectListsForTables(
            $tableIds,
            $selectedColumnIds,
            $seedProviderMap,
            $rewriteContext,
            $seedMapContext,
            $job,
            $sourceAlias
        );

        $lines = [];
        if ($warnings !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Inline Masking Notes';
            $lines[] = '-- Some methods could not be converted to inline expressions; those columns pass through unchanged.';
            $lines[] = $this->commentDivider('=');
            foreach ($warnings as $warning) {
                $lines[] = '-- ' . $warning;
            }
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $lines = array_merge($lines, $this->renderJobTableClonesForTables($rewriteContext, $tableIds));

        return trim(implode(PHP_EOL, $lines));
    }

    public function buildConstraintsOnlyForJob(AnonymizationJobs $job): string
    {
        $tableIds = $this->resolveConstraintTableIds($job);
        if ($tableIds === []) {
            return '';
        }

        $tables = AnonymousSiebelTable::query()
            ->withTrashed()
            ->with(['schema.database'])
            ->whereIn('id', $tableIds)
            ->orderBy('table_name')
            ->get();

        if ($tables->isEmpty()) {
            return '';
        }

        $rewriteContext = $this->buildRewriteContextForTables($tables, $job, true);

        $lines = [];
        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Constraints Only: ' . $job->name;
        $lines[] = '-- Generated: ' . now()->toDateString() . ' ' . now()->toTimeString();
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $pkStatements = $this->buildPrimaryKeyStatements($rewriteContext);
        if ($pkStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Primary Keys';
            $lines[] = '-- Add ROW_ID primary keys so foreign keys can be recreated.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $pkStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $fkStatements = $this->buildForeignKeyStatements($rewriteContext);
        if ($fkStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Foreign Keys';
            $lines[] = '-- Recreate parent/child relationships within the target schema.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $fkStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        if ($pkStatements === [] && $fkStatements === []) {
            $lines[] = '-- No constraints were generated for the selected scope.';
        }

        return trim(implode(PHP_EOL, $lines));
    }

    protected function resolveConstraintTableIds(AnonymizationJobs $job): array
    {
        $tableIds = DB::table('anonymization_job_tables')
            ->where('job_id', $job->id)
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($tableIds !== []) {
            return $tableIds;
        }

        $tableIds = DB::table('anonymization_job_columns as ajc')
            ->join('anonymous_siebel_columns as c', 'c.id', '=', 'ajc.column_id')
            ->where('ajc.job_id', $job->id)
            ->distinct()
            ->pluck('c.table_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($tableIds !== []) {
            return $tableIds;
        }

        $schemaIds = DB::table('anonymization_job_schemas')
            ->where('job_id', $job->id)
            ->pluck('schema_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($schemaIds !== []) {
            return DB::table('anonymous_siebel_tables')
                ->whereIn('schema_id', $schemaIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
        }

        $databaseIds = DB::table('anonymization_job_databases')
            ->where('job_id', $job->id)
            ->pluck('database_id')
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($databaseIds !== []) {
            $schemaIds = DB::table('anonymous_siebel_schemas')
                ->whereIn('database_id', $databaseIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($schemaIds !== []) {
                return DB::table('anonymous_siebel_tables')
                    ->whereIn('schema_id', $schemaIds)
                    ->pluck('id')
                    ->map(fn($id) => (int) $id)
                    ->filter(fn($id) => $id > 0)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    protected function buildRewriteContextForTables(Collection $tables, ?AnonymizationJobs $job, bool $skipLongDetection = false): array
    {
        $targetSchema = $this->targetSchemaForJob($job);
        $tablePrefix = $this->tablePrefixForJob($job);
        $targetTableMode = $this->normalizeJobOption($job?->target_table_mode) ?: 'prefixed';
        $defaultRelationKind = $this->normalizeRelationKind($job?->target_relation_kind ?? 'table');

        if (! $targetSchema || ! $tablePrefix) {
            return [];
        }

        $tables = $tables
            ->filter()
            ->unique(fn($t) => (int) $t->getKey())
            ->values();

        $tableColumns = $skipLongDetection ? [] : $this->columnsByTableWithTypes($tables);

        $tablesById = [];
        $rawReplace = [];

        foreach ($tables as $table) {
            $schema = $table->getRelationValue('schema');
            $database = $schema?->getRelationValue('database');
            $sourceSchema = $schema?->schema_name;
            $sourceTable = $table->table_name;
            $tableId = (int) $table->getKey();

            if (! $sourceSchema || ! $sourceTable) {
                continue;
            }

            if ($skipLongDetection) {
                $selectList = '*';
                $longColumns = [];
            } else {
                $selectList = $this->buildCloneSelectList($tableColumns[$tableId] ?? collect());
                $longColumns = $this->longColumnsForTable($tableColumns[$tableId] ?? collect());
            }

            $targetTableName = $this->targetTableNameForSourceTable($sourceTable, $tablePrefix, $targetTableMode);
            $targetTable = $this->oracleIdentifier($targetTableName);
            $targetQualified = $targetSchema . '.' . $targetTable;
            $sourceQualified = $sourceSchema . '.' . $sourceTable;
            $relationKind = $this->normalizeRelationKind($table->target_relation_kind ?? $defaultRelationKind);

            $tablesById[$tableId] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
                'select_list' => $selectList,
                'long_columns' => $longColumns,
                'target_relation_kind' => $relationKind,
            ];

            $rawReplace[$sourceQualified] = $targetQualified;

            if ($database?->database_name) {
                $rawReplace[$database->database_name . '.' . $sourceQualified] = $targetQualified;
            }
        }

        if ($rawReplace !== []) {
            uksort($rawReplace, fn($a, $b) => strlen($b) <=> strlen($a));
        }

        return [
            'target_schema' => $targetSchema,
            'table_prefix' => $tablePrefix,
            'target_table_mode' => $targetTableMode,
            'target_relation_kind' => $defaultRelationKind,
            'tables_by_id' => $tablesById,
            'raw_replace' => $rawReplace,
            'seed_store_mode' => trim((string) ($job?->seed_store_mode ?? '')),
            'seed_store_schema' => trim((string) ($job?->seed_store_schema ?? '')),
            'seed_store_prefix' => trim((string) ($job?->seed_store_prefix ?? '')),
            'seed_map_hygiene_mode' => trim((string) ($job?->seed_map_hygiene_mode ?? '')),
            'job_seed' => (string) ($job?->job_seed ?? ''),
            'job_seed_literal' => $this->oracleStringLiteral($job?->job_seed),
        ];
    }

    public function buildFromColumns(Collection $columns, ?AnonymizationJobs $job = null): string
    {
        // Clear method cache at start of each build to ensure fresh state
        $this->methodCache = [];
        $this->currentJobStrategy = $job?->strategy;

        if ($columns->isEmpty()) {
            return '';
        }

        // Ensure we're working with an Eloquent Collection for lazy loading support
        if (! $columns instanceof \Illuminate\Database\Eloquent\Collection) {
            $columns = new \Illuminate\Database\Eloquent\Collection($columns->all());
        }

        // Load essential relations if not already loaded
        $columns->loadMissing([
            'anonymizationMethods',
            'anonymizationRule.methods',
            'table.schema.database',
            'dataType',
            'parentColumns',
        ]);

        // Load packages on the already-loaded methods collection
        // Need to hydrate as Eloquent Collection since pluck/flatten/filter returns base Collection
        $methodItems = $columns->pluck('anonymizationMethods')->flatten()->filter()->unique('id');
        if ($methodItems->isNotEmpty()) {
            $methods = AnonymizationMethods::hydrate($methodItems->values()->all());
            $methods->loadMissing('packages');
        }

        $ordered = $this->topologicallySortColumns($columns);

        if ($ordered->isEmpty()) {
            return '';
        }

        $seedProviders = $this->resolveSeedProviders($ordered);

        $rewriteContext = $this->buildJobTableRewriteContext($ordered, $job);
        $rewriteContext['masking_mode'] = 'inline';
        $rewriteContext['source_alias'] = 'src';
        $seedMapContext = $this->jobUsesSeedMapPlaceholders($ordered)
            ? $this->buildSeedMapContext($ordered, $seedProviders, $rewriteContext, $job)
            : [];

        $lines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);

        $contractReview = $this->validateSeedContracts($ordered, $seedProviders, $seedMapContext);

        if ($contractReview['errors']->isNotEmpty() || $contractReview['warnings']->isNotEmpty()) {
            $lines = array_merge($lines, $this->renderContractReview($contractReview));

            if ($contractReview['errors']->isNotEmpty()) {
                $lines[] = '-- SQL generation halted due to blocking seed contract violations.';
                return trim(implode(PHP_EOL, $lines));
            }
        }

        $packages = $this->collectPackagesFromColumns($ordered);

        if ($packages->isNotEmpty()) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Package Dependencies';
            $lines[] = '-- Ordered for deterministic exports';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';

            foreach ($packages as $package) {
                $lines[] = $this->commentDivider('-');
                $lines[] = '-- Package: ' . $package->display_label;

                if ($package->summary) {
                    $lines[] = '-- ' . trim($package->summary);
                }

                foreach ($package->compiledSqlBlocks() as $block) {
                    $lines[] = trim($this->rewritePackageSqlBlock((string) $block, $rewriteContext));
                    $lines[] = '';
                }
            }

            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $seedProviderMap = [];
        foreach ($seedProviders as $columnId => $provider) {
            $seedProviderMap[(int) $columnId] = [
                'provider_id' => isset($provider['provider']) ? (int) ($provider['provider']?->id ?? 0) : 0,
                'expression' => $provider['expression'] ?? null,
            ];
        }

        $tableIds = array_values(array_filter(array_map('intval', array_keys($rewriteContext['tables_by_id'] ?? []))));
        $inlineWarnings = $this->applyInlineMaskedSelectListsForTables(
            $tableIds,
            $ordered->pluck('id')->map(fn($id) => (int) $id)->all(),
            $seedProviderMap,
            $rewriteContext,
            $seedMapContext,
            $job,
            (string) ($rewriteContext['source_alias'] ?? 'src')
        );
        if ($inlineWarnings !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Inline Masking Notes';
            $lines[] = '-- Some methods could not be converted to inline expressions; those columns pass through unchanged.';
            $lines[] = $this->commentDivider('=');
            foreach ($inlineWarnings as $warning) {
                $lines[] = '-- ' . $warning;
            }
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        // Seed maps must be created before the table clone CTAS statements run,
        // because the inline SELECT expressions in those CTAS queries reference the
        // seed map tables via {{SEED_MAP_LOOKUP}} (or via LEFT JOINs added by
        // applyInlineMaskedSelectListsForTables). Building them here from the source
        // tables is safe — they have no dependency on the target clones.
        $seedMapStatements = $this->renderSeedMapTables($seedMapContext);
        if ($seedMapStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Seed Maps (relationship preservation)';
            $lines[] = '-- Created before table clones so inline FK lookup expressions can reference them.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $seedMapStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $preMaskSql = trim((string) ($job?->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Pre-mask SQL';
            $lines[] = '-- Runs after seed maps are created, before table clones.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $preMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);
        if ($tableCloneStatements !== []) {
            $lines = array_merge($lines, $tableCloneStatements);
        }

        $postMaskSql = trim((string) ($job?->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Post-mask SQL';
            $lines[] = '-- Runs after table clones are created.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $postMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $pkStatements = $this->buildPrimaryKeyStatements($rewriteContext);
        if ($pkStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Primary Keys';
            $lines[] = '-- Add ROW_ID primary keys so foreign keys can be recreated.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $pkStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $fkStatements = $this->buildForeignKeyStatements($rewriteContext);
        if ($fkStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Foreign Keys';
            $lines[] = '-- Recreate parent/child relationships within the target schema.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $fkStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $hygiene = $this->renderSeedMapHygieneSection($seedMapContext, $job);
        if ($hygiene !== []) {
            $lines = array_merge($lines, $hygiene);
        }

        // Commit final DML so generated tables/views and seed maps are persisted.
        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Finalize';
        $lines[] = 'COMMIT;';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return trim(implode(PHP_EOL, $lines));
    }

    protected function jobUsesSeedMapPlaceholders(Collection $columns): bool
    {
        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $sqlBlock = strtolower((string) ($method?->sql_block ?? ''));

            if ($sqlBlock === '') {
                continue;
            }

            if (
                str_contains($sqlBlock, strtolower('{{SEED_MAP_LOOKUP}}'))
                || str_contains($sqlBlock, strtolower('{{SEED_MAP_TABLE}}'))
            ) {
                return true;
            }
        }

        return false;
    }

    protected function validateSeedContracts(Collection $columns, array $seedProviders = [], array $seedMapContext = []): array
    {
        $selected = $columns->keyBy('id');
        $errors = collect();
        $warnings = collect();
        $issues = collect();

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $mode = $column->seed_contract_mode;
            $columnLabel = $this->describeColumn($column);

            $pushIssue = function (string $severity, string $message, string $code = 'seed_contract') use (&$issues, $column) {
                $issues->push([
                    'column_id' => $column->id,
                    'severity' => $severity,
                    'code' => $code,
                    'message' => $message,
                ]);
            };

            if (! $method) {
                // Columns without methods will pass through unchanged (clone-only behavior).
                // This is never a blocking error - it's informational at most.
                // The column data will simply be copied as-is from the source.
                if ($column->anonymization_required) {
                    $detail = $columnLabel . ': No anonymization method attached. Column is marked as requiring anonymization but will pass through unchanged.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'missing_method');
                }
                // Columns not requiring anonymization and without a method are fine - no warning needed.
                continue;
            }

            $usesSeedPlaceholder = $this->methodUsesSeedPlaceholders($method);

            // Warn if a method is marked requires_seed but doesn't reference any seed placeholders.
            if ($method->requires_seed && ! $usesSeedPlaceholder) {
                $detail = $columnLabel . ': Method ' . $method->name
                    . ' is marked as requiring a seed, but its SQL block does not reference ' . implode(', ', self::SEED_PLACEHOLDERS) . '.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'seed_placeholder_missing');
            }

            // Only enforce explicit mode mismatches; inferred emit/consume behavior doesn't require manual flags.
            if ($mode === SeedContractMode::SOURCE && ! $method->emits_seed) {
                $detail = $columnLabel . ': Declared as a seed source but method ' . $method->name . ' is not marked as emitting a seed.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'source_mismatch');
            }

            if ($mode === SeedContractMode::CONSUMER && ! $method->requires_seed) {
                $detail = $columnLabel . ': Declared as a seed consumer but method ' . $method->name . ' does not require a seed.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'consumer_mismatch');
            }

            if ($mode === SeedContractMode::CONSUMER && $method->requires_seed && ! $usesSeedPlaceholder) {
                $detail = $columnLabel . ': Declared as a seed consumer and method ' . $method->name
                    . ' requires a seed, but the method SQL does not reference seed placeholders. Seed wiring may be ineffective.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'consumer_placeholder_missing');
            }

            if ($mode === SeedContractMode::COMPOSITE && ! $method->supports_composite_seed) {
                $detail = $columnLabel . ': Declared as a composite seed but method ' . $method->name . ' is not composite-ready.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'composite_mismatch');
            }

            if (! $this->columnRequiresSeed($column, $method)) {
                continue;
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();

            if ($parents->isEmpty()) {
                $fallbackProvider = $this->inferSeedProviderFromSelection($column, $columns);

                if ($fallbackProvider) {
                    $detail = $columnLabel . ': No explicit parent dependency set; using inferred seed provider ' . $this->describeColumn($fallbackProvider) . '.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'implicit_seed_provider');
                    continue;
                }

                $fallback = $this->defaultConsumerSeedFallback($column);
                $detail = $columnLabel . ': No explicit parent dependency set; defaulting seed expression to '
                    . ($fallback['expression'] ?? 'tgt.ROW_ID') . ' (' . ($fallback['reason'] ?? 'table ROW_ID fallback') . ').';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'seed_fallback_row_id');
                continue;
            }

            foreach ($parents as $parentRelation) {
                $mandatory = $parentRelation->pivot->is_seed_mandatory ?? true;
                $bundleDescriptor = $this->describeSeedBundle($parentRelation);
                $parentLabel = $this->describeColumn($parentRelation);
                $selectedParent = $selected->get($parentRelation->id);

                if (! $selectedParent) {
                    // Allow EXTERNAL parents to be omitted from the job selection (non-blocking).
                    if ($mandatory && $parentRelation->seed_contract_mode !== SeedContractMode::EXTERNAL) {
                        $fallbackProvider = $this->inferSeedProviderFromSelection($column, $columns);

                        if ($fallbackProvider) {
                            $detail = $columnLabel . ': Requires parent ' . $parentLabel . $bundleDescriptor
                                . ' but it is not included in this job; using inferred seed provider ' . $this->describeColumn($fallbackProvider) . ' instead.';
                            $warnings->push($detail);
                            $pushIssue('warning', $detail, 'missing_parent_selection');
                        } else {
                            $detail = $columnLabel . ': Requires parent ' . $parentLabel . $bundleDescriptor . ' but it is not included in this job.';
                            $warnings->push($detail);
                            $pushIssue('warning', $detail, 'missing_parent_selection');
                        }
                    } else {
                        $detail = $columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor . ' is not included; verify the external seed handshake.';
                        $warnings->push($detail);
                        $pushIssue('warning', $detail, 'external_seed_handshake');
                    }
                    continue;
                }

                $parentMethod = $this->resolveMethodForColumn($selectedParent);

                if (! $this->columnProvidesSeed($selectedParent, $parentMethod)) {
                    // If a selected parent doesn't provide a seed, warn (don't block) so SQL can still generate.
                    $detail = $columnLabel . ': Parent ' . $parentLabel . $bundleDescriptor
                        . ' is referenced as a dependency but is not declared as a seed provider; using it anyway. Consider marking it as SOURCE.';
                    $warnings->push($detail);
                    $pushIssue('warning', $detail, 'parent_not_seed');
                }

                // Require an explicit seed expression when a provider participates in a generated seed map.
                if (isset(($seedMapContext['providers'] ?? [])[(int) $selectedParent->id])) {
                    $expr = trim((string) ($selectedParent->seed_contract_expression ?? ''));
                    if ($expr === '') {
                        $defaultExpr = $this->seedExpressionForProvider($selectedParent);
                        $detail = $columnLabel . ': Parent ' . $parentLabel
                            . ' is used as a seed provider but is missing seed_contract_expression; defaulting to ' . $defaultExpr . '.';
                        $warnings->push($detail);
                        $pushIssue('warning', $detail, 'seed_provider_expression_missing');
                    }
                }
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'issues' => $issues,
        ];
    }

    protected function renderContractReview(array $review): array
    {
        $lines = [
            $this->commentDivider('='),
            '-- Seed Contract Review',
            $this->commentDivider('-'),
        ];

        if ($review['errors']->isNotEmpty()) {
            $lines[] = '-- Blocking issues:';
            foreach ($review['errors'] as $error) {
                $lines[] = '--   * ' . $error;
            }
        }

        if ($review['warnings']->isNotEmpty()) {
            if ($review['errors']->isNotEmpty()) {
                $lines[] = '--';
            }

            $lines[] = '-- Warnings:';
            foreach ($review['warnings'] as $warning) {
                $lines[] = '--   * ' . $warning;
            }
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function columnProvidesSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        // Treat explicit seed modes as authoritative, regardless of method flags.
        if ($mode === SeedContractMode::SOURCE || $mode === SeedContractMode::COMPOSITE || $mode === SeedContractMode::EXTERNAL) {
            return true;
        }

        return (bool) ($method?->emits_seed);
    }

    protected function columnRequiresSeed(AnonymousSiebelColumn $column, ?AnonymizationMethods $method): bool
    {
        $mode = $column->seed_contract_mode;

        // Treat explicit seed modes as authoritative, regardless of method flags.
        if ($mode === SeedContractMode::CONSUMER || $mode === SeedContractMode::COMPOSITE) {
            return true;
        }

        // Use method SQL placeholders as the source of truth for whether a seed is actually consumed.
        return (bool) ($method?->requires_seed) && $this->methodUsesSeedPlaceholders($method);
    }

    protected function resolveSeedProviders(Collection $columns): array
    {
        $providers = [];

        $seedEmitters = $columns
            ->filter(function (AnonymousSiebelColumn $candidate) {
                return $this->columnProvidesSeed($candidate, $this->resolveMethodForColumn($candidate));
            })
            ->values();

        $emittersByTable = $seedEmitters
            ->groupBy(fn(AnonymousSiebelColumn $c) => (int) ($c->table_id ?? 0));

        // Build UPPER(SCHEMA|TABLE) → [UPPER(COLUMN_NAME) => column model] index.
        // seedProviderForColumn uses this to resolve FK parents from related_columns
        // metadata when no explicit anonymous_siebel_column_dependencies row exists.
        $selectedBySchemaTable = [];
        foreach ($columns as $col) {
            $tbl = $col->getRelationValue('table');
            $sch = $tbl?->getRelationValue('schema');
            if (! $tbl || ! $sch) {
                continue;
            }
            $key     = strtoupper((string) ($sch->schema_name ?? '')) . '|' . strtoupper((string) ($tbl->table_name ?? ''));
            $colName = strtoupper((string) ($col->column_name ?? ''));
            if ($key !== '|' && $colName !== '') {
                $selectedBySchemaTable[$key][$colName] = $col;
            }
        }

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $provider = $this->seedProviderForColumn($column, $method, $columns, $emittersByTable, $seedEmitters, $selectedBySchemaTable);

            $expression = $provider ? $this->seedExpressionForProvider($provider) : null;

            if ($expression === null && $this->columnRequiresSeed($column, $method)) {
                $expression = $this->defaultConsumerSeedExpression($column);
            }

            if ($expression === null) {
                $expression = $this->seedExpressionForProvider($column);
            }

            $providers[$column->id] = [
                'provider' => $provider,
                'expression' => $expression,
            ];
        }

        return $providers;
    }

    protected function seedProviderForColumn(
        AnonymousSiebelColumn $column,
        ?AnonymizationMethods $method,
        Collection $selectedColumns,
        Collection $emittersByTable,
        Collection $seedEmitters,
        array $selectedBySchemaTable = []
    ): ?AnonymousSiebelColumn {
        if (! $this->columnRequiresSeed($column, $method)) {
            return $this->columnProvidesSeed($column, $method) ? $column : null;
        }

        // 1. Prefer an explicitly configured parent via anonymous_siebel_column_dependencies.
        $parents = $column->getRelationValue('parentColumns') ?? collect();

        $selectedById = $selectedColumns->keyBy('id');

        foreach ($parents as $parent) {
            $selectedParent = $selectedById->get($parent->id);
            if (! $selectedParent) {
                continue;
            }

            // Use the explicitly selected parent; validation can still warn if it isn't a seed provider.
            return $selectedParent;
        }

        if ($parents->isNotEmpty()) {
            // Explicit dependencies exist but none were found in the selection.
            return null;
        }

        // 2. Infer the parent from FK relationship metadata (related_columns / related_columns_raw).
        //    This covers the common case where no manual dependency exists but the imported Siebel
        //    schema metadata already declares an outbound FK pointing to a parent ROW_ID column.
        //    Without this, seed maps are never resolved for FK columns, causing {{SEED_MAP_LOOKUP}}
        //    to substitute as an empty string and the NVL to silently return the original value.
        if ($selectedBySchemaTable !== []) {
            $fkProvider = $this->inferProviderFromRelatedColumnMeta($column, $selectedBySchemaTable);
            if ($fkProvider !== null) {
                return $fkProvider;
            }
        }

        // 3. Fall back to another seed emitter in the same table when no FK metadata resolves.
        $tableId = (int) ($column->table_id ?? 0);
        $sameTable = ($emittersByTable->get($tableId) ?? collect())
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        if ($sameTable->count() === 1) {
            return $sameTable->first();
        }

        if ($sameTable->count() > 1) {
            // If multiple emitters exist, pick the first by column name for determinism.
            return $sameTable->sortBy('column_name')->first();
        }

        // 4. If only a single global seed emitter exists (small jobs), use it.
        $global = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        if ($global->count() === 1) {
            return $global->first();
        }

        // No safe provider found; let validation surface the issue.
        return null;
    }

    /**
     * Infer the seed-providing ROW_ID column for an FK consumer using the
     * imported related_columns / related_columns_raw metadata (populated during CSV sync).
     *
     * Used as a fallback when no explicit anonymous_siebel_column_dependencies row exists.
     * Without this, FK columns whose parent is in a different table have no provider, causing
     * {{SEED_MAP_LOOKUP}} to resolve to an empty string — Oracle treats '' as NULL, the NVL
     * falls back to the original FK value, and all cross-table relationships break.
     *
     * @param AnonymousSiebelColumn $column               The FK consumer column
     * @param array                 $selectedBySchemaTable Index: UPPER('SCHEMA|TABLE') => [UPPER(col_name) => AnonymousSiebelColumn]
     * @return AnonymousSiebelColumn|null  The parent ROW_ID column, or null if not in the selection
     */
    protected function inferProviderFromRelatedColumnMeta(
        AnonymousSiebelColumn $column,
        array $selectedBySchemaTable
    ): ?AnonymousSiebelColumn {
        if ($selectedBySchemaTable === []) {
            return null;
        }

        $relationships = $this->resolveForeignKeyRelationships($column);
        foreach ($relationships as $rel) {
            $direction = strtoupper((string) ($rel['direction'] ?? 'OUTBOUND'));
            if ($direction !== 'OUTBOUND') {
                continue;
            }

            $schema = strtoupper(trim((string) ($rel['schema'] ?? '')));
            $table  = strtoupper(trim((string) ($rel['table'] ?? '')));
            $pCol   = strtoupper(trim((string) ($rel['column'] ?? 'ROW_ID')));

            // Siebel FK columns always point to ROW_ID; skip anything else.
            if ($schema === '' || $table === '' || $pCol !== 'ROW_ID') {
                continue;
            }

            $parentModel = $selectedBySchemaTable[$schema . '|' . $table]['ROW_ID'] ?? null;
            if ($parentModel instanceof AnonymousSiebelColumn) {
                return $parentModel;
            }
        }

        return null;
    }

    protected function defaultConsumerSeedExpression(AnonymousSiebelColumn $column): string
    {
        $fallback = $this->defaultConsumerSeedFallback($column);

        return (string) ($fallback['expression'] ?? 'tgt.ROW_ID');
    }

    protected function defaultConsumerSeedFallback(AnonymousSiebelColumn $column): array
    {
        $columnName = trim((string) ($column->column_name ?? ''));
        $parents = $column->getRelationValue('parentColumns') ?? collect();

        $hasRowIdParent = $parents->contains(function ($parent): bool {
            return strtoupper(trim((string) ($parent->column_name ?? ''))) === 'ROW_ID';
        });

        if ($hasRowIdParent && $columnName !== '') {
            return [
                'expression' => 'tgt.' . $columnName,
                'reason' => 'explicit ROW_ID parent dependency',
            ];
        }

        $tableId = (int) ($column->table_id ?? 0);
        if ($tableId > 0) {
            $columnNames = $this->tableColumnNamesForSeedFallback($tableId);

            if (isset($columnNames['ROW_ID'])) {
                return [
                    'expression' => 'tgt.ROW_ID',
                    'reason' => 'table ROW_ID fallback',
                ];
            }
        }

        if ($tableId > 0) {
            if (isset($columnNames['PAR_ROW_ID'])) {
                return [
                    'expression' => 'COALESCE(tgt.PAR_ROW_ID, tgt.ROW_ID)',
                    'reason' => 'secondary fallback via PAR_ROW_ID (ROW_ID preferred)',
                ];
            }

            foreach (['PARENT_ROW_ID', 'PARENT_ID', 'PAR_ID'] as $candidate) {
                if (isset($columnNames[$candidate])) {
                    return [
                        'expression' => 'COALESCE(tgt.' . $candidate . ', tgt.ROW_ID)',
                        'reason' => 'secondary fallback via ' . $candidate . ' (ROW_ID preferred)',
                    ];
                }
            }
        }

        return [
            'expression' => 'tgt.ROW_ID',
            'reason' => 'table ROW_ID fallback',
        ];
    }

    protected function tableColumnNamesForSeedFallback(int $tableId): array
    {
        if (isset($this->tableColumnNameCache[$tableId])) {
            return $this->tableColumnNameCache[$tableId];
        }

        $names = AnonymousSiebelColumn::query()
            ->where('table_id', $tableId)
            ->pluck('column_name')
            ->map(fn($name) => strtoupper(trim((string) $name)))
            ->filter(fn($name) => $name !== '')
            ->flip()
            ->all();

        $this->tableColumnNameCache[$tableId] = is_array($names) ? $names : [];

        return $this->tableColumnNameCache[$tableId];
    }

    // Resolve {{SEED_EXPR}} for a seed provider (explicit expression, else default to tgt.<column>).
    protected function seedExpressionForProvider(AnonymousSiebelColumn $provider): string
    {
        $expression = trim((string) ($provider->seed_contract_expression ?? ''));

        if ($expression !== '') {
            return $expression;
        }

        return 'tgt.' . ($provider->column_name ?? 'seed');
    }

    /**
     * Compute the anonymized (transformed) expression for a seed-providing column.
     *
     * This builds the expression that transforms the column's original value into
     * its anonymized form. Used as the `new_value` in seed map tables so that
     * child FK columns can look up the new (anonymized) value by the old (original)
     * value. Without this, seed maps store identity mappings (old=new) and FK
     * lookups return the original un-anonymized value.
     *
     * @param AnonymousSiebelColumn      $provider       The seed-providing column
     * @param AnonymizationMethods|null   $method         The method assigned to this column
     * @param array                       $rewriteContext The rewrite context
     * @param string                      $alias          The source alias (usually 'src')
     * @return string|null  The anonymized expression, or null if not extractable
     */
    protected function anonymizedExpressionForSeedMap(
        AnonymousSiebelColumn $provider,
        ?AnonymizationMethods $method,
        array $rewriteContext,
        string $alias = 'src'
    ): ?string {
        if (! $method) {
            return null;
        }

        $sqlBlock = trim((string) ($method->sql_block ?? ''));
        if ($sqlBlock === '') {
            return null;
        }

        // Skip exclude, no-op, and deferred methods — they don't have inline expressions.
        if ($this->isExcludeMethod($sqlBlock) || $this->isNoOpMethod($sqlBlock) || $this->isDeferredMethod($sqlBlock)) {
            return null;
        }

        $expressionTemplate = $this->extractUpdateExpressionFromTemplate($sqlBlock);
        if (! $expressionTemplate) {
            return null;
        }

        // Apply placeholder replacements targeting the provider column.
        // Pass empty seed map context — a provider doesn't reference its own seed map.
        $rendered = $this->applyPlaceholders(
            $expressionTemplate,
            $provider,
            ['provider' => $provider, 'expression' => $alias . '.' . ($provider->column_name ?? 'seed')],
            $rewriteContext,
            [],      // empty seed map context
            $alias,
            true     // useSourceTable: reference source schema/table in the seed map query
        );

        return trim($rendered) !== '' ? $rendered : null;
    }

    protected function inferSeedProviderFromSelection(AnonymousSiebelColumn $column, Collection $selectedColumns): ?AnonymousSiebelColumn
    {
        $method = $this->resolveMethodForColumn($column);

        if (! $this->columnRequiresSeed($column, $method)) {
            return null;
        }

        $seedEmitters = $selectedColumns
            ->filter(function (AnonymousSiebelColumn $candidate) {
                return $this->columnProvidesSeed($candidate, $this->resolveMethodForColumn($candidate));
            })
            ->values();

        if ($seedEmitters->isEmpty()) {
            return null;
        }

        $tableId = (int) ($column->table_id ?? 0);
        $sameTable = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => (int) ($c->table_id ?? 0) === $tableId && $c->id !== $column->id)
            ->values();

        if ($sameTable->count() === 1) {
            return $sameTable->first();
        }

        if ($sameTable->count() > 1) {
            return $sameTable->sortBy('column_name')->first();
        }

        $global = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        return $global->count() === 1 ? $global->first() : null;
    }

    protected function describeSeedBundle(AnonymousSiebelColumn $parent): string
    {
        $pivot = $parent->pivot;

        if (! $pivot) {
            return '';
        }

        $label = trim((string) ($pivot->seed_bundle_label ?? ''));
        $components = $pivot->seed_bundle_components ?? null;

        if (is_string($components)) {
            $decoded = json_decode($components, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $components = $decoded;
            } else {
                $components = null;
            }
        }

        if ($components instanceof Collection) {
            $components = $components->all();
        }

        $componentLabel = null;

        if (is_array($components) && $components !== []) {
            $componentLabel = implode(' + ', array_map('strval', $components));
        }

        $parts = array_filter([$label, $componentLabel]);

        return $parts === [] ? '' : ' [' . implode(' | ', $parts) . ']';
    }

    protected function collectPackagesFromColumns(Collection $columns): Collection
    {
        return $columns
            ->map(fn(AnonymousSiebelColumn $column) => $this->resolveMethodForColumn($column))
            ->filter()
            ->flatMap(fn(AnonymizationMethods $method) => $method->packages ?? collect())
            ->filter()
            ->unique(fn($package) => $package->id)
            ->values();
    }

    protected function renderSqlBlocksForColumns(string $template, Collection $columns, array $seedProviders = [], array $rewriteContext = [], array $seedMapContext = []): array
    {
        $output = [];

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $rendered = $this->applyPlaceholders($template, $column, $seedProviders[$column->id] ?? null, $rewriteContext, $seedMapContext);

            $output[] = '-- Applies to: ' . $this->describeColumn($column);
            $output[] = $rendered;
            $output[] = '';
        }

        return $output === [] ? [$template] : $output;
    }

    protected function applyPlaceholders(
        string $template,
        AnonymousSiebelColumn $column,
        ?array $seedProvider = null,
        array $rewriteContext = [],
        array $seedMapContext = [],
        string $alias = 'tgt',
        bool $useSourceTable = false
    ): string {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $tableId = (int) ($table?->getKey() ?? $column->table_id ?? 0);
        $tableMap = $rewriteContext['tables_by_id'] ?? [];
        $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;

        $renderSchemaName = $useSourceTable
            ? ($mapped['source_schema'] ?? ($schema?->schema_name ?? ''))
            : ($mapped['target_schema'] ?? ($schema?->schema_name ?? ''));
        $renderTableName = $useSourceTable
            ? ($mapped['source_table'] ?? ($table?->table_name ?? ''))
            : ($mapped['target_table'] ?? ($table?->table_name ?? ''));

        $qualifiedTable = collect([
            // Omit database prefix when rewriting so SQL runs in the target schema.
            $mapped ? null : $database?->database_name,
            $renderSchemaName,
            $renderTableName,
        ])->filter()->implode('.');

        $seedColumnName = $seedProvider['provider']?->column_name ?? $column->column_name ?? '';
        $seedSourceLabel = isset($seedProvider['provider'])
            ? $this->describeColumn($seedProvider['provider'])
            : $this->describeColumn($column);
        $seedQualified = $this->seedQualifiedReference($column, $seedProvider['provider'] ?? null, $qualifiedTable, $rewriteContext);
        $seedExpression = $seedProvider['expression'] ?? ($alias . '.' . $seedColumnName);
        if ($alias !== 'tgt' && is_string($seedExpression)) {
            $seedExpression = str_replace('tgt.', $alias . '.', $seedExpression);
        }

        $seedMap = $this->seedMapForColumn($seedProvider['provider'] ?? null, $seedMapContext);
        $seedMapTable = $seedMap['seed_map_table'] ?? '';
        $seedLookupColumnRef = $alias . '.' . ($column->column_name ?? '');
        $seedMapLookup = $seedMapTable !== ''
            ? '(SELECT sm.new_value FROM ' . $seedMapTable . ' sm WHERE sm.old_value = ' . $seedLookupColumnRef . ' AND ROWNUM = 1)'
            : $seedLookupColumnRef;

        $jobSeed = $rewriteContext['job_seed'] ?? '';
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";

        $columnMaxLength = $this->oracleColumnMaxLength($column);
        $columnName = $column->column_name ?? '';
        $columnMaxLengthExpr = ($columnMaxLength > 0 && $columnMaxLength < 4000)
            ? (string) $columnMaxLength
            : ($columnName !== '' ? ('length(' . $alias . '.' . $columnName . ')') : '4000');

        $replacements = [
            '{{TABLE}}' => $qualifiedTable ?: ($renderTableName ?: ($table?->table_name ?? '{{TABLE}}')),
            '{{TABLE_NAME}}' => $renderTableName,
            '{{SCHEMA}}' => $renderSchemaName,
            '{{DATABASE}}' => $mapped ? '' : ($database?->database_name ?? ''),
            '{{COLUMN}}' => $column->column_name ?? '',
            '{{COLUMN_MAX_LEN}}' => (string) $columnMaxLength,
            '{{COLUMN_MAX_LEN_EXPR}}' => $columnMaxLengthExpr,
            '{{ALIAS}}' => $alias,
            '{{SEED_COLUMN}}' => $seedColumnName,
            '{{SEED_SOURCE}}' => $seedSourceLabel,
            '{{SEED_SOURCE_QUALIFIED}}' => $seedQualified,
            '{{SEED_EXPR}}' => $seedExpression,
            '{{SEED_MAP_TABLE}}' => $seedMapTable,
            '{{SEED_MAP_LOOKUP}}' => $seedMapLookup,
            '{{JOB_SEED}}' => is_string($jobSeed) ? $jobSeed : '',
            '{{JOB_SEED_LITERAL}}' => is_string($jobSeedLiteral) ? $jobSeedLiteral : "''",
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        if ($alias !== 'tgt') {
            $rendered = str_replace('tgt.', $alias . '.', $rendered);
        }

        $rawReplace = $rewriteContext['raw_replace'] ?? [];
        if (! $useSourceTable && $rawReplace !== []) {
            $rendered = str_replace(array_keys($rawReplace), array_values($rawReplace), $rendered);
        }

        $rendered = $this->enforceDeterministicRandomUsage($rendered, $column, $rewriteContext);
        $rendered = $this->rewriteAnonymizationPackageOwner($rendered);

        return $rendered;
    }

    protected function deterministicShuffleRowKeyExpression(AnonymousSiebelColumn $column): string
    {
        $tableId = (int) ($column->table_id ?? 0);
        $columnNames = $tableId > 0 ? $this->tableColumnNamesForSeedFallback($tableId) : [];

        foreach (['ROW_ID', 'PAR_ROW_ID', 'PARENT_ROW_ID', 'PARENT_ID', 'PAR_ID', 'ID'] as $candidate) {
            if (isset($columnNames[$candidate])) {
                return 'TO_CHAR(' . $candidate . ')';
            }
        }

        return 'TO_CHAR(ROWID)';
    }

    protected function deterministicShuffleOrderExpression(AnonymousSiebelColumn $column, array $rewriteContext): string
    {
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";
        if (! is_string($jobSeedLiteral) || $jobSeedLiteral === '') {
            $jobSeedLiteral = "''";
        }

        $columnName = strtoupper(trim((string) ($column->column_name ?? 'COLUMN')));
        if ($columnName === '') {
            $columnName = 'COLUMN';
        }

        $rowKeyExpression = $this->deterministicShuffleRowKeyExpression($column);

        return "STANDARD_HASH({$jobSeedLiteral} || '|SHUFFLE|{$columnName}|' || {$rowKeyExpression}, 'SHA256')";
    }

    protected function enforceDeterministicRandomUsage(string $sql, AnonymousSiebelColumn $column, array $rewriteContext): string
    {
        if (! str_contains(strtoupper($sql), 'DBMS_RANDOM.VALUE')) {
            return $sql;
        }

        $orderExpression = $this->deterministicShuffleOrderExpression($column, $rewriteContext);

        $rewritten = preg_replace('/DBMS_RANDOM\.VALUE\s*\(\s*\)/i', $orderExpression, $sql);
        if ($rewritten === null) {
            return $sql;
        }

        $rewritten = preg_replace('/DBMS_RANDOM\.VALUE\b/i', $orderExpression, $rewritten);

        return $rewritten ?? $sql;
    }

    protected function extractUpdateExpression(string $renderedSql, string $columnName): ?string
    {
        $columnName = trim($columnName);

        if ($columnName === '') {
            return null;
        }

        $pattern = '/\bset\s+(?:[A-Za-z0-9_]+\.)?' . preg_quote($columnName, '/') . '\s*=\s*(.+?)(?:\s+where\b|;)/is';

        if (! preg_match($pattern, $renderedSql, $matches)) {
            return null;
        }

        $expression = trim((string) ($matches[1] ?? ''));
        $expression = rtrim($expression, ';');

        return $expression === '' ? null : $expression;
    }

    /**
     * Sentinel returned by inlineMaskedExpressionForColumn when the method
     * signals that the column should be omitted from the CTAS SELECT list.
     */
    protected const INLINE_EXCLUDE = '__EXCLUDE__';

    /**
     * Sentinel returned when the method is a documented no-op
     * (e.g. Seed Provider, Preserve NULL Semantics).
     */
    protected const INLINE_NOOP = '__NOOP__';

    /**
     * Sentinel prefix for methods that require post-CTAS execution
     * (e.g. MERGE-based shuffles).  The full sql_block is appended.
     */
    protected const INLINE_DEFERRED_PREFIX = '__DEFERRED__';

    protected function inlineMaskedExpressionForColumn(
        AnonymousSiebelColumn $column,
        ?AnonymizationMethods $method,
        array $seedProviders,
        array $rewriteContext,
        array $seedMapContext,
        string $sourceAlias = 'src'
    ): ?string {
        if (! $method) {
            return null;
        }

        $sqlBlock = trim((string) ($method->sql_block ?? ''));
        if ($sqlBlock === '') {
            return null;
        }

        // Exclude: column should not appear in CTAS at all.
        if ($this->isExcludeMethod($sqlBlock)) {
            return self::INLINE_EXCLUDE;
        }

        // No-op: pass through, no warning.
        if ($this->isNoOpMethod($sqlBlock)) {
            return self::INLINE_NOOP;
        }

        // MERGE / non-UPDATE: defer to post-CTAS execution.
        if ($this->isDeferredMethod($sqlBlock)) {
            return self::INLINE_DEFERRED_PREFIX . $sqlBlock;
        }

        $expressionTemplate = $this->extractUpdateExpressionFromTemplate($sqlBlock);
        if (! $expressionTemplate) {
            return null;
        }

        return $this->applyPlaceholders(
            $expressionTemplate,
            $column,
            $seedProviders[$column->id] ?? null,
            $rewriteContext,
            $seedMapContext,
            $sourceAlias,
            true
        );
    }

    /**
     * Detect methods that signal column exclusion from the anonymized copy.
     * These sql_blocks contain only SQL comments and no executable statements.
     */
    protected function isExcludeMethod(string $sqlBlock): bool
    {
        // Strip comment lines and blank lines; if nothing remains, it's a comment-only (exclude/doc) method.
        $stripped = preg_replace('/^\s*--.*$/m', '', $sqlBlock);
        $stripped = trim((string) $stripped);

        return $stripped === '';
    }

    /**
     * Detect no-op methods (seed providers, documentation-only methods).
     * These have only comments or trivial SELECT from DUAL.
     */
    protected function isNoOpMethod(string $sqlBlock): bool
    {
        $stripped = preg_replace('/^\s*--.*$/m', '', $sqlBlock);
        $stripped = trim((string) $stripped);

        if ($stripped === '') {
            return true; // Comment-only — could also be exclude, but handled above.
        }

        // SELECT ... FROM DUAL is purely informational.
        return (bool) preg_match('/^\s*SELECT\b.+?\bFROM\s+DUAL\s*;?\s*$/is', $stripped);
    }

    /**
     * Detect methods that use MERGE or other non-UPDATE DML that cannot
     * be inlined into a CTAS SELECT expression.
     */
    protected function isDeferredMethod(string $sqlBlock): bool
    {
        $stripped = preg_replace('/^\s*--.*$/m', '', $sqlBlock);
        $stripped = trim((string) $stripped);

        return (bool) preg_match('/^\s*MERGE\s+INTO\b/is', $stripped);
    }

    protected function extractUpdateExpressionFromTemplate(string $template): ?string
    {
        // Primary pattern: UPDATE ... SET {{COLUMN}} = <expr> WHERE {{COLUMN}} IS NOT NULL
        $pattern = '/\bset\s+\{\{COLUMN\}\}\s*=\s*(.+?)\s*where\s+\{\{COLUMN\}\}\s+is\s+not\s+null\b\s*;?/is';

        if (preg_match($pattern, $template, $matches)) {
            $expression = trim((string) ($matches[1] ?? ''));
            return $expression === '' ? null : $expression;
        }

        // Fallback: UPDATE ... SET {{COLUMN}} = <expr> followed by WHERE on other
        // conditions, or terminated by semicolon / end-of-string (e.g. nullable-safe).
        $fallback = '/\bset\s+\{\{COLUMN\}\}\s*=\s*(.+?)\s*(?:where\b|;|$)/is';

        if (preg_match($fallback, $template, $matches)) {
            $expression = trim((string) ($matches[1] ?? ''));
            return $expression === '' ? null : $expression;
        }

        return null;
    }

    protected function applyInlineMaskedSelectListsForTables(
        array $tableIds,
        array $selectedColumnIds,
        array $seedProviderMap,
        array &$rewriteContext,
        array $seedMapContext,
        ?AnonymizationJobs $job = null,
        string $sourceAlias = 'src'
    ): array {
        $tableIds = array_values(array_unique(array_filter(array_map('intval', $tableIds))));
        if ($tableIds === []) {
            return [];
        }

        $selectedLookup = array_flip(array_values(array_unique(array_filter(array_map('intval', $selectedColumnIds)))));
        $nullUnselectedColumns = $this->shouldNullUnselectedColumns($job);

        // Pre-load seed provider models once (small set — only providers for selected columns).
        $providerIds = [];
        foreach ($seedProviderMap as $columnId => $provider) {
            $columnId = (int) $columnId;
            if (! isset($selectedLookup[$columnId])) {
                continue;
            }
            $providerId = (int) ($provider['provider_id'] ?? 0);
            if ($providerId > 0) {
                $providerIds[$providerId] = true;
            }
        }

        $providerModels = collect();
        if ($providerIds !== []) {
            $providerModels = AnonymousSiebelColumn::query()
                ->with(['table.schema.database', 'dataType'])
                ->whereIn('id', array_keys($providerIds))
                ->get()
                ->keyBy('id');
        }

        $seedProviders = [];
        foreach ($seedProviderMap as $columnId => $provider) {
            $columnId = (int) $columnId;
            if (! isset($selectedLookup[$columnId])) {
                continue;
            }
            $providerId = (int) ($provider['provider_id'] ?? 0);
            $seedProviders[$columnId] = [
                'provider' => $providerId > 0 ? $providerModels->get($providerId) : null,
                'expression' => $provider['expression'] ?? null,
            ];
        }

        // Pre-load job pivot rows once (small set — only columns attached to the job).
        $jobPivotLookup = [];
        if ($job?->id) {
            $jobPivotRows = DB::table('anonymization_job_columns')
                ->where('job_id', $job->id)
                ->select(['column_id', 'anonymization_method_id'])
                ->get();

            foreach ($jobPivotRows as $row) {
                $jobPivotLookup[(int) ($row->column_id ?? 0)] = (int) ($row->anonymization_method_id ?? 0);
            }
        }

        // Process tables in chunks to keep peak memory bounded.
        // Each chunk loads columns with relations, builds select lists, then releases memory.
        $warnings = [];
        $requiredPackageRefs = $rewriteContext['required_package_refs'] ?? [];

        foreach (array_chunk($tableIds, self::TABLE_COLUMN_CHUNK_SIZE) as $tableChunk) {
            $columns = AnonymousSiebelColumn::query()
                ->with([
                    'anonymizationMethods',
                    'anonymizationRule.methods',
                    'table.schema.database',
                    'dataType',
                    'parentColumns',
                ])
                ->whereIn('table_id', $tableChunk)
                ->orderBy('column_name')
                ->get();

            if ($columns->isEmpty()) {
                continue;
            }

            // Apply job pivot data to loaded columns.
            if ($jobPivotLookup !== []) {
                foreach ($columns as $column) {
                    $colId = (int) $column->id;
                    if (isset($jobPivotLookup[$colId])) {
                        $column->setRelation('pivot', (object) [
                            'anonymization_method_id' => $jobPivotLookup[$colId],
                        ]);
                    }
                }
            }

            $columnsByTable = $columns->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

            foreach ($tableChunk as $tableId) {
                $tableColumns = $columnsByTable->get((int) $tableId, collect());
                if ($tableColumns->isEmpty()) {
                    continue;
                }

                $selectParts = [];
                $deferredStatements = [];
                $selectedSourceColumns = [];
                foreach ($tableColumns as $column) {
                    if ($this->isLongColumn($column)) {
                        continue;
                    }

                    $isSelected = isset($selectedLookup[(int) $column->id]);
                    $columnName = $column->column_name ?? '';
                    if ($columnName === '') {
                        continue;
                    }

                    if ($isSelected) {
                        $selectedSourceColumns[] = Str::upper($columnName);
                    }

                    if (! $isSelected && $nullUnselectedColumns) {
                        $selectParts[] = $this->oracleNullExpressionForColumn($column) . ' ' . $columnName;
                        continue;
                    }

                    $method = $isSelected ? $this->resolveMethodForColumn($column) : null;

                    if ($method) {
                        $requiredPackageRefs = $this->collectPackageRefsFromSqlBlock(
                            (string) ($method->sql_block ?? ''),
                            $requiredPackageRefs
                        );
                    }

                    $expression = $this->inlineMaskedExpressionForColumn(
                        $column,
                        $method,
                        $seedProviders,
                        $rewriteContext,
                        $seedMapContext,
                        $sourceAlias
                    );

                    // Exclude: omit column from CTAS entirely.
                    if ($expression === self::INLINE_EXCLUDE) {
                        continue;
                    }

                    // No-op: pass column through unchanged, no warning.
                    if ($expression === self::INLINE_NOOP) {
                        $selectParts[] = $sourceAlias . '.' . $columnName . ' ' . $columnName;
                        continue;
                    }

                    // Deferred (MERGE/shuffle): pass column through in CTAS, collect
                    // the full statement for post-CTAS execution.
                    if ($expression !== null && str_starts_with($expression, self::INLINE_DEFERRED_PREFIX)) {
                        $selectParts[] = $sourceAlias . '.' . $columnName . ' ' . $columnName;
                        $rawBlock = substr($expression, strlen(self::INLINE_DEFERRED_PREFIX));
                        $deferredStatements[] = $this->applyPlaceholders(
                            $rawBlock,
                            $column,
                            $seedProviders[$column->id] ?? null,
                            $rewriteContext,
                            $seedMapContext,
                            'tgt',
                            false
                        );
                        continue;
                    }

                    if (! $expression) {
                        if ($method) {
                            $warnings[] = $this->describeColumn($column)
                                . ': method SQL could not be inlined; column will pass through unchanged.';
                        }
                        $expression = $sourceAlias . '.' . ($column->column_name ?? '');
                    }

                    $expression = $this->normalizeInlineExpressionForCtas($expression, $column);

                    $lookupPlan = $this->buildInlineReferenceLookupPlan(
                        $tableId,
                        $column,
                        $method,
                        $expression,
                        $rewriteContext,
                        $sourceAlias,
                        $seedProviders[$column->id] ?? null,
                        $seedMapContext
                    );

                    if ($lookupPlan !== null) {
                        $selectParts[] = $lookupPlan['select_expression'] . ' ' . $columnName;

                        $existingPreCtas = $rewriteContext['tables_by_id'][$tableId]['pre_ctas_statements'] ?? [];
                        $rewriteContext['tables_by_id'][$tableId]['pre_ctas_statements'] = array_merge(
                            is_array($existingPreCtas) ? $existingPreCtas : [],
                            $lookupPlan['pre_ctas_statements']
                        );

                        $existingJoins = $rewriteContext['tables_by_id'][$tableId]['post_source_joins'] ?? [];
                        $rewriteContext['tables_by_id'][$tableId]['post_source_joins'] = array_values(array_unique(array_merge(
                            is_array($existingJoins) ? $existingJoins : [],
                            $lookupPlan['join_clauses'] ?? []
                        )));

                        continue;
                    }

                    // Optimisation: convert {{SEED_MAP_LOOKUP}} correlated subqueries to an
                    // indexed LEFT JOIN. The correlated form executes a probe per row; a
                    // JOIN lets Oracle use the seed map's PRIMARY KEY (old_value) in a
                    // single hash or nested-loops pass — much faster on large FK tables.
                    // A unique alias per (seed_map_table, column) pair handles tables with
                    // multiple FK columns that each reference a different parent seed map.
                    if ($method && str_contains((string) ($method->sql_block ?? ''), '{{SEED_MAP_LOOKUP}}')) {
                        $seedProvider = $seedProviders[$column->id] ?? null;
                        $seedMapEntry = $this->seedMapForColumn(
                            $seedProvider['provider'] ?? null,
                            $seedMapContext
                        );
                        $smTable = $seedMapEntry['seed_map_table'] ?? '';
                        if ($smTable !== '') {
                            $smAlias = 'smj_' . substr(md5($smTable . '|' . $columnName), 0, 8);
                            $joinClause = 'LEFT JOIN ' . $smTable . ' ' . $smAlias
                                . ' ON ' . $smAlias . '.old_value = ' . $sourceAlias . '.' . $columnName;

                            $selectParts[] = 'NVL(' . $smAlias . '.new_value, '
                                . $sourceAlias . '.' . $columnName . ') ' . $columnName;

                            $existingJoins = $rewriteContext['tables_by_id'][$tableId]['post_source_joins'] ?? [];
                            $rewriteContext['tables_by_id'][$tableId]['post_source_joins'] = array_values(array_unique(array_merge(
                                is_array($existingJoins) ? $existingJoins : [],
                                [$joinClause]
                            )));
                            continue;
                        }
                    }

                    $selectParts[] = $expression . ' ' . $columnName;
                }

                // Store deferred (post-CTAS) statements for this table.
                if ($deferredStatements !== []) {
                    $rewriteContext['tables_by_id'][$tableId]['deferred_statements'] = $deferredStatements;
                }

                $rewriteContext['tables_by_id'][$tableId]['null_unselected_columns'] = $nullUnselectedColumns;
                $rewriteContext['tables_by_id'][$tableId]['selected_source_columns'] = array_values(array_unique($selectedSourceColumns));
                $rewriteContext['tables_by_id'][$tableId]['select_list'] = $selectParts === []
                    ? ''
                    : "\n    " . implode(",\n    ", $selectParts);
            }

            // Release column models for this chunk before loading the next.
            unset($columns, $columnsByTable);
        }

        if ($requiredPackageRefs !== []) {
            $rewriteContext['required_package_refs'] = $requiredPackageRefs;
        }

        return $warnings;
    }

    protected function normalizeInlineExpressionForCtas(string $expression, AnonymousSiebelColumn $column): string
    {
        if (preg_match('/^\s*NULL\s*$/i', $expression)) {
            return $this->oracleNullExpressionForColumn($column);
        }

        return $expression;
    }

    protected function collectPackageRefsFromSqlBlock(string $sqlBlock, array $refs): array
    {
        $sqlBlock = $this->rewriteAnonymizationPackageOwner($sqlBlock);

        if (trim($sqlBlock) === '') {
            return $refs;
        }

        preg_match_all(
            '/\b([A-Za-z][A-Za-z0-9_$#]*)\.([A-Za-z][A-Za-z0-9_$#]*)\.[A-Za-z][A-Za-z0-9_$#]*\b/',
            $sqlBlock,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $owner = $this->mapAnonymizationPackageOwner((string) ($match[1] ?? ''));
            $package = strtoupper((string) ($match[2] ?? ''));

            if ($owner === '' || $package === '' || ! str_starts_with($package, 'PKG_ANON_')) {
                continue;
            }

            $key = $owner . '.' . $package;
            $refs[$key] = [
                'owner' => $owner,
                'package' => $package,
            ];
        }

        return $refs;
    }

    protected function buildInlineReferenceLookupPlan(
        int $tableId,
        AnonymousSiebelColumn $column,
        ?AnonymizationMethods $method,
        string $expression,
        array $rewriteContext,
        string $sourceAlias,
        ?array $seedProvider,
        array $seedMapContext
    ): ?array {
        if (! $method || trim($expression) === '') {
            return null;
        }

        $sqlBlock = strtoupper((string) ($method->sql_block ?? ''));
        if (! ((bool) ($method->requires_seed)) || ! preg_match('/\bPKG_ANON_[A-Z0-9_$#]+\b/', $sqlBlock)) {
            return null;
        }

        $mapping = $rewriteContext['tables_by_id'][$tableId] ?? null;
        if (! is_array($mapping)) {
            return null;
        }

        $targetSchema = (string) ($mapping['target_schema'] ?? ($rewriteContext['target_schema'] ?? ''));
        $sourceQualified = (string) ($mapping['source_qualified'] ?? '');
        $sourceTable = (string) ($mapping['source_table'] ?? '');
        $tablePrefix = (string) ($rewriteContext['table_prefix'] ?? 'JOB');
        $columnName = (string) ($column->column_name ?? '');

        if ($targetSchema === '' || $sourceQualified === '' || $sourceTable === '' || $columnName === '') {
            return null;
        }

        $lookupTableName = $this->oracleIdentifier($tablePrefix . '_REFMAP_' . strtoupper($sourceTable) . '_' . strtoupper($columnName));
        $lookupQualified = $targetSchema . '.' . $lookupTableName;
        $lookupIndexName = $this->oracleIdentifier($tablePrefix . '_RFX_' . strtoupper($sourceTable) . '_' . strtoupper($columnName));
        $joinAlias = 'ref_' . substr(md5($lookupTableName), 0, 8);

        $lookupAlias = 'lkp_src';
        $lookupExpr = str_replace($sourceAlias . '.', $lookupAlias . '.', $expression);
        $lookupExpr = trim($lookupExpr);

        $seedExprLookup = $this->applyPlaceholders(
            '{{SEED_EXPR}}',
            $column,
            $seedProvider,
            $rewriteContext,
            $seedMapContext,
            $lookupAlias,
            true
        );
        $seedExprSource = $this->applyPlaceholders(
            '{{SEED_EXPR}}',
            $column,
            $seedProvider,
            $rewriteContext,
            $seedMapContext,
            $sourceAlias,
            true
        );

        $seedExprLookup = trim($seedExprLookup) !== '' ? $seedExprLookup : ($lookupAlias . '.ROW_ID');
        $seedExprSource = trim($seedExprSource) !== '' ? $seedExprSource : ($sourceAlias . '.ROW_ID');

        if ($lookupExpr === '') {
            return null;
        }

        $preCtas = [
            '-- Build deterministic reference map for ' . $sourceQualified . '.' . $columnName,
            'BEGIN',
            "  EXECUTE IMMEDIATE 'DROP TABLE {$lookupQualified} PURGE';",
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -942 THEN RAISE; END IF;',
            'END;',
            '/',
            'CREATE TABLE ' . $lookupQualified . ' AS',
            'SELECT DISTINCT',
            '  TO_CHAR(' . $seedExprLookup . ') AS seed_key,',
            '  ' . $lookupAlias . '.' . $columnName . ' AS old_value,',
            '  ' . $lookupExpr . ' AS new_value',
            'FROM ' . $sourceQualified . ' ' . $lookupAlias,
            'WHERE ' . $lookupAlias . '.' . $columnName . ' IS NOT NULL;',
            'BEGIN',
            "  EXECUTE IMMEDIATE 'DROP INDEX {$targetSchema}.{$lookupIndexName}';",
            'EXCEPTION',
            '  WHEN OTHERS THEN',
            '    IF SQLCODE != -1418 THEN RAISE; END IF;',
            'END;',
            '/',
            'CREATE INDEX ' . $targetSchema . '.' . $lookupIndexName,
            'ON ' . $lookupQualified . ' (seed_key, old_value);',
            '',
        ];

        $joinClause = 'LEFT JOIN ' . $lookupQualified . ' ' . $joinAlias
            . ' ON ' . $joinAlias . '.seed_key = TO_CHAR(' . $seedExprSource . ')'
            . ' AND ' . $joinAlias . '.old_value = ' . $sourceAlias . '.' . $columnName;

        $selectExpression = $joinAlias . '.new_value';

        return [
            'select_expression' => $selectExpression,
            'pre_ctas_statements' => $preCtas,
            'join_clauses' => [$joinClause],
        ];
    }

    protected function buildJobTableRewriteContext(Collection $columns, ?AnonymizationJobs $job, bool $skipLongDetection = false): array
    {
        $targetSchema = $this->targetSchemaForJob($job);
        $tablePrefix = $this->tablePrefixForJob($job);
        $targetTableMode = $this->normalizeJobOption($job?->target_table_mode) ?: 'prefixed';
        $defaultRelationKind = $this->normalizeRelationKind($job?->target_relation_kind ?? 'table');
        $hasExplicitColumns = false;

        if ($job?->id) {
            $hasExplicitColumns = DB::table('anonymization_job_columns')
                ->where('job_id', (int) $job->id)
                ->exists();
        }

        if (! $targetSchema || ! $tablePrefix) {
            return [];
        }

        $tables = collect();
        $grantScopeTableIds = [];

        // For FULL jobs without explicit column selections, clone every table in scope.
        // If explicit columns were selected, respect that narrower scope.
        if ($job && $job->job_type === AnonymizationJobs::TYPE_FULL && ! $hasExplicitColumns) {
            $schemaIds = $this->schemaIdsForJobOrSelection($job, $columns);

            if ($schemaIds !== []) {
                $tables = AnonymousSiebelTable::query()
                    ->withTrashed()
                    ->with(['schema.database'])
                    ->whereIn('schema_id', $schemaIds)
                    ->orderBy('table_name')
                    ->get();
            }
        }

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $table = $column->getRelationValue('table');
            if ($table) {
                $tables->push($table);

                $tableId = (int) $table->getKey();
                if ($tableId > 0) {
                    $grantScopeTableIds[$tableId] = true;
                }
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTable = $parent->getRelationValue('table');
                if ($parentTable) {
                    $tables->push($parentTable);

                    $parentTableId = (int) $parentTable->getKey();
                    if ($parentTableId > 0) {
                        $grantScopeTableIds[$parentTableId] = true;
                    }
                }
            }
        }

        $tables = $tables
            ->filter()
            ->unique(fn($t) => (int) $t->getKey())
            ->values();

        $tableColumns = $skipLongDetection ? [] : $this->columnsByTableWithTypes($tables);

        $tablesById = [];
        $rawReplace = [];

        foreach ($tables as $table) {
            $schema = $table->getRelationValue('schema');
            $database = $schema?->getRelationValue('database');
            $sourceSchema = $schema?->schema_name;
            $sourceTable = $table->table_name;
            $tableId = (int) $table->getKey();

            if (! $sourceSchema || ! $sourceTable) {
                continue;
            }

            if ($skipLongDetection) {
                $selectList = '*';
                $longColumns = [];
            } else {
                $selectList = $this->buildCloneSelectList($tableColumns[$tableId] ?? collect());
                $longColumns = $this->longColumnsForTable($tableColumns[$tableId] ?? collect());
            }

            $targetTableName = $this->targetTableNameForSourceTable($sourceTable, $tablePrefix, $targetTableMode);
            $targetTable = $this->oracleIdentifier($targetTableName);
            $targetQualified = $targetSchema . '.' . $targetTable;
            $sourceQualified = $sourceSchema . '.' . $sourceTable;
            $relationKind = $this->normalizeRelationKind($table->target_relation_kind ?? $defaultRelationKind);

            $tablesById[$tableId] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
                'select_list' => $selectList,
                'long_columns' => $longColumns,
                'target_relation_kind' => $relationKind,
            ];

            // Rewrite qualified source names to their target working-copy equivalents.
            $rawReplace[$sourceQualified] = $targetQualified;

            if ($database?->database_name) {
                $rawReplace[$database->database_name . '.' . $sourceQualified] = $targetQualified;
            }
        }

        // Sort longer matches first to reduce accidental partial rewrites.
        if ($rawReplace !== []) {
            uksort($rawReplace, fn($a, $b) => strlen($b) <=> strlen($a));
        }

        return [
            'target_schema' => $targetSchema,
            'table_prefix' => $tablePrefix,
            'target_table_mode' => $targetTableMode,
            'target_relation_kind' => $defaultRelationKind,
            'table_scope_mode' => $hasExplicitColumns ? 'explicit-columns' : ($job?->job_type === AnonymizationJobs::TYPE_FULL ? 'full-schema' : 'selection-derived'),
            'tables_by_id' => $tablesById,
            'grant_scope_table_ids' => array_values(array_keys($grantScopeTableIds)),
            'raw_replace' => $rawReplace,
            'seed_store_mode' => trim((string) ($job?->seed_store_mode ?? '')),
            'seed_store_schema' => trim((string) ($job?->seed_store_schema ?? '')),
            'seed_store_prefix' => trim((string) ($job?->seed_store_prefix ?? '')),
            'seed_map_hygiene_mode' => trim((string) ($job?->seed_map_hygiene_mode ?? '')),
            'job_seed' => (string) ($job?->job_seed ?? ''),
            'job_seed_literal' => $this->oracleStringLiteral($job?->job_seed),
        ];
    }

    protected function targetTableNameForSourceTable(string $sourceTable, string $tablePrefix, string $mode): string
    {
        $mode = $this->normalizeJobOption($mode);

        if ($mode === 'exact') {
            return $sourceTable;
        }

        if ($mode === 'anon') {
            // In anon mode, write into ANON_* tables (including INITIAL_* -> ANON_*).
            if (Str::startsWith($sourceTable, 'ANON_')) {
                return $sourceTable;
            }

            if (Str::startsWith($sourceTable, 'INITIAL_')) {
                return 'ANON_' . Str::after($sourceTable, 'INITIAL_');
            }

            return 'ANON_' . $sourceTable;
        }

        // In prefixed mode, write into <prefix>_<source_table> working copies.
        return $tablePrefix . '_' . $sourceTable;
    }

    protected function renderSeedMapHygieneSection(array $seedMapContext, ?AnonymizationJobs $job): array
    {
        $seedStoreMode = $this->normalizeJobOption($job?->seed_store_mode);
        $mode = $this->normalizeJobOption($job?->seed_map_hygiene_mode);

        if ($seedStoreMode !== 'persistent' || $mode === '' || $mode === 'none') {
            return [];
        }

        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers) || $providers === []) {
            return [];
        }

        $tables = [];
        foreach ($providers as $provider) {
            if (($provider['seed_map_persistence'] ?? null) !== 'persistent') {
                continue;
            }

            $table = trim((string) ($provider['seed_map_table'] ?? ''));
            if ($table !== '') {
                $tables[$table] = true;
            }
        }

        $tables = array_keys($tables);
        sort($tables);

        if ($tables === []) {
            return [];
        }

        $commented = $mode !== 'execute';
        $prefix = $commented ? '-- ' : '';

        $lines = [
            $this->commentDivider('='),
            '-- Seed Map Hygiene (Oracle MGMT_DM_TT analogue)',
            '-- Seed maps store old→new value mappings and should be removed before exporting/cloning to less-secure environments.',
            '-- Mode: ' . ($commented ? 'commented' : 'execute'),
            $this->commentDivider('='),
            '',
        ];

        foreach ($tables as $table) {
            $lines[] = $prefix . '-- Drop seed map: ' . $table;
            $lines[] = $prefix . 'BEGIN';
            $lines[] = $prefix . "  EXECUTE IMMEDIATE 'DROP TABLE {$table} PURGE';";
            $lines[] = $prefix . 'EXCEPTION';
            $lines[] = $prefix . '  WHEN OTHERS THEN';
            $lines[] = $prefix . '    IF SQLCODE NOT IN (-942, -12083) THEN RAISE; END IF;';
            $lines[] = $prefix . 'END;';
            $lines[] = $prefix . '/';
            $lines[] = '';
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function buildImpactReport(
        Collection $columns,
        ?AnonymizationJobs $job,
        array $seedProviders,
        array $rewriteContext,
        array $seedMapContext
    ): array {
        $lines = [
            $this->commentDivider('='),
            '-- Impact Report (heuristics)',
            '-- This section mirrors Oracle-style preflight guidance using metadata + method templates only.',
            '-- It does not inspect real data or constraints; treat warnings as prompts for review.',
            $this->commentDivider('='),
            '',
        ];

        $warnings = [];

        $seedStoreMode = $this->normalizeJobOption($job?->seed_store_mode);
        if ($seedStoreMode === 'persistent') {
            $warnings[] = 'Persistent seed maps are enabled: ensure the seed store schema is access-controlled and dropped before distributing masked datasets.';
        }

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $sqlBlock = strtolower(trim((string) ($method?->sql_block ?? '')));

            if ($sqlBlock === '') {
                continue;
            }

            $label = $this->describeColumn($column);
            $dataType = strtoupper(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));
            $length = (int) ($column->data_length ?? $column->char_length ?? 0);
            $mode = $column->seed_contract_mode;

            $isKeyLike = preg_match('/(^|_)(id|rowid|row_id|integration_id|key)(_|$)/i', (string) ($column->column_name ?? '')) === 1;

            $isConditional = str_contains($sqlBlock, 'case ') || (str_contains($sqlBlock, ' when ') && str_contains($sqlBlock, ' then '));
            $usesSeedMapLookup = str_contains($sqlBlock, strtolower('{{SEED_MAP_LOOKUP}}'));
            $usesHash = str_contains($sqlBlock, 'standard_hash') || str_contains($sqlBlock, 'dbms_crypto') || str_contains($sqlBlock, 'sha');
            $usesRandom = str_contains($sqlBlock, 'dbms_random') || str_contains($sqlBlock, 'random');
            $usesRegexp = str_contains($sqlBlock, 'regexp_replace');

            if ($isConditional) {
                if (($column->getRelationValue('parentColumns') ?? collect())->isNotEmpty() || $mode === SeedContractMode::CONSUMER || $mode === SeedContractMode::COMPOSITE) {
                    $warnings[] = $label . ': Conditional masking detected; column participates in a dependency/seed graph. Oracle warns of conditional “bleeding” with duplicates + dependents. Prefer deterministic mapping tables (seed maps) over inline CASE for key fields.';
                } else {
                    $warnings[] = $label . ': Conditional masking detected. Review duplicates and ensure conditional branches do not produce collisions.';
                }
            }

            if ($usesSeedMapLookup && $seedStoreMode !== 'persistent') {
                $warnings[] = $label . ': Uses {{SEED_MAP_LOOKUP}} but seed store is not persistent. Cross-run determinism requires persistent seed maps.';
            }

            if ($usesHash && ($dataType === 'VARCHAR' || $dataType === 'VARCHAR2' || str_contains($dataType, 'CHAR'))) {
                if ($length > 0 && $length < 32) {
                    $warnings[] = $label . ": Hashing detected with {$dataType}({$length}). Risk of truncation/collisions. Ensure the expression output fits the column (e.g., use RAWTOHEX + adequate length) and verify unique constraints.";
                } else {
                    $warnings[] = $label . ": Hashing detected. Ensure expression output type/length matches {$dataType}" . ($length > 0 ? "({$length})" : '') . ' and does not violate uniqueness constraints.';
                }
            }

            if ($usesRandom && $isKeyLike && $length > 0 && $length <= 8) {
                $warnings[] = $label . ": Randomization detected on key-like column with short length ({$length}). Uniqueness capacity may be too small; consider deterministic seed mapping or a larger target width.";
            }

            if ($usesRegexp) {
                $warnings[] = $label . ': REGEXP-based masking detected. Ensure all original values match the regex to preserve one-to-one mapping and avoid uniqueness violations.';
            }
        }

        $warnings = array_values(array_unique(array_filter(array_map('trim', $warnings))));

        if ($warnings === []) {
            $lines[] = '-- No heuristic warnings generated.';
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            return $lines;
        }

        foreach ($warnings as $warning) {
            $lines[] = '-- WARNING: ' . $warning;
        }

        $lines[] = '';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function schemaIdsForJobOrSelection(AnonymizationJobs $job, Collection $selectedColumns): array
    {
        $schemaIds = DB::table('anonymization_job_schemas')
            ->where('job_id', (int) $job->getKey())
            ->pluck('schema_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($schemaIds !== []) {
            return $schemaIds;
        }

        $tableIds = DB::table('anonymization_job_tables')
            ->where('job_id', (int) $job->getKey())
            ->pluck('table_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($tableIds !== []) {
            return DB::table('anonymous_siebel_tables')
                ->whereIn('id', $tableIds)
                ->pluck('schema_id')
                ->map(fn($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $databaseIds = DB::table('anonymization_job_databases')
            ->where('job_id', (int) $job->getKey())
            ->pluck('database_id')
            ->map(fn($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($databaseIds !== []) {
            return DB::table('anonymous_siebel_schemas')
                ->whereIn('database_id', $databaseIds)
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return $selectedColumns
            ->map(fn(AnonymousSiebelColumn $column) => (int) ($column->getRelationValue('table')?->schema_id ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function targetSchemaForJob(?AnonymizationJobs $job): ?string
    {
        $override = trim((string) ($job?->target_schema ?? ''));

        if ($override !== '') {
            // Sanitize the schema override into a safe Oracle identifier.
            return $this->oracleIdentifier(Str::upper($override));
        }

        $type = $job?->job_type;

        return match ($type) {
            AnonymizationJobs::TYPE_FULL => 'SBLANONF',
            AnonymizationJobs::TYPE_PARTIAL => 'SBLANONP',
            default => null,
        };
    }

    protected function tablePrefixForJob(?AnonymizationJobs $job): ?string
    {
        $name = trim((string) ($job?->name ?? ''));

        if ($name === '') {
            return null;
        }

        $parts = preg_split('/[^A-Za-z0-9]+/', $name) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));

        if ($parts === []) {
            return null;
        }

        $parts = array_map(fn($part) => Str::studly($part), $parts);

        // Convert the job name into a safe prefix like Foo_Bar (Studly parts joined by _).
        return implode('_', $parts);
    }

    protected function renderJobTableClones(array $rewriteContext): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];

        if ($tablesById === []) {
            return [];
        }

        $skipCommonPreflight = (bool) ($rewriteContext['skip_common_preflight'] ?? false);

        $lines = [];
        if (! $skipCommonPreflight) {
            $lines = $this->buildSourceAccessPreflightForClones($rewriteContext);
            $lines = array_merge($lines, $this->buildConditionalPackageBootstrap($rewriteContext));
            $lines = array_merge($lines, $this->buildRequiredPackagePreflight($rewriteContext));
        }
        foreach ($tablesById as $mapping) {
            $source = $mapping['source_qualified'] ?? null;
            $target = $mapping['target_qualified'] ?? null;
            if (! $source || ! $target) {
                continue;
            }

            $lines[] = $this->commentDivider('-');
            $lines[] = '-- Safety check: source and target must not be identical';
            $lines[] = $this->commentDivider('-');
            $lines[] = 'BEGIN';
            $lines[] = "  IF UPPER('" . str_replace("'", "''", $source) . "') = UPPER('" . str_replace("'", "''", $target) . "') THEN";
            $lines[] = "    RAISE_APPLICATION_ERROR(-20044, 'Unsafe mapping: source and target are identical (" . str_replace("'", "''", $source) . "). Use a different target schema or table mode.');";
            $lines[] = '  END IF;';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = '';

            $selectList = $mapping['select_list'] ?? '*';
            $longColumns = $mapping['long_columns'] ?? [];
            $relationKind = $this->normalizeRelationKind($mapping['target_relation_kind'] ?? ($rewriteContext['target_relation_kind'] ?? 'table'));
            $inlineMasking = ($rewriteContext['masking_mode'] ?? '') === 'inline';
            $sourceAlias = $inlineMasking ? ($rewriteContext['source_alias'] ?? 'src') : null;
            $preCtasStatements = $mapping['pre_ctas_statements'] ?? [];
            $postSourceJoins = $mapping['post_source_joins'] ?? [];

            if ($relationKind === 'view') {
                if ($preCtasStatements !== []) {
                    $lines[] = $this->commentDivider('-');
                    $lines[] = '-- Pre-build reference maps for ' . $target;
                    $lines[] = '-- Materializes deterministic lookup values once per distinct source value.';
                    $lines[] = $this->commentDivider('-');
                    foreach ($preCtasStatements as $stmt) {
                        $lines[] = $stmt;
                    }
                    $lines[] = '';
                }

                $lines[] = $this->commentDivider('=');
                $lines[] = '-- Drop target view/table if it exists';
                $lines[] = $this->commentDivider('=');
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'DROP VIEW {$target}';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE NOT IN (-942, -12083) THEN';
                $lines[] = '      RAISE;';
                $lines[] = '    END IF;';
                $lines[] = 'END;';
                $lines[] = '/';
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$target} CASCADE CONSTRAINTS PURGE';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE != -942 THEN';
                $lines[] = '      RAISE;';
                $lines[] = '    END IF;';
                $lines[] = 'END;';
                $lines[] = '/';
                $lines[] = $this->commentDivider('=');
                $lines[] = '';

                $lines[] = $this->commentDivider('=');
                $lines[] = '-- Create anonymized view';
                $lines[] = '-- NOTE:';
                $lines[] = '--  * Uses SELECT * to avoid invalid identifier failures';
                $lines[] = '--  * Copies only columns visible to this user';
                if (is_array($longColumns) && $longColumns !== []) {
                    $lines[] = '--  * LONG columns are omitted to avoid ORA-00997';
                }
                $lines[] = $this->commentDivider('=');

                if (! is_string($selectList) || trim($selectList) === '') {
                    $lines[] = '-- Skipped: no non-LONG columns available for view.';
                } elseif (trim($selectList) === '*' && (empty($longColumns))) {
                    $lines[] = 'CREATE OR REPLACE VIEW ' . $target . ' AS';
                    $lines[] = 'SELECT *';
                    $fromLines = $this->buildCloneFromLines($source, $sourceAlias, $postSourceJoins);
                    $lines = array_merge($lines, $fromLines);
                    $lines[count($lines) - 1] .= ';';
                } else {
                    $lines[] = 'CREATE OR REPLACE VIEW ' . $target . ' AS';
                    $lines[] = 'SELECT ' . $selectList;
                    $fromLines = $this->buildCloneFromLines($source, $sourceAlias, $postSourceJoins);
                    $lines = array_merge($lines, $fromLines);
                    $lines[count($lines) - 1] .= ';';
                }
                $lines[] = $this->commentDivider('=');
                $lines[] = '';

                // Emit deferred (post-create) statements for this table (e.g. MERGE-based shuffles).
                $deferred = $mapping['deferred_statements'] ?? [];
                if ($deferred !== []) {
                    $lines[] = $this->commentDivider('-');
                    $lines[] = '-- Post-create operations for ' . $target;
                    $lines[] = '-- These methods cannot be expressed as inline SELECT expressions.';
                    $lines[] = $this->commentDivider('-');
                    foreach ($deferred as $stmt) {
                        $lines[] = $stmt;
                        $lines[] = '';
                    }
                }
                continue;
            }

            if ($preCtasStatements !== []) {
                $lines[] = $this->commentDivider('-');
                $lines[] = '-- Pre-build reference maps for ' . $target;
                $lines[] = '-- Materializes deterministic lookup values once per distinct source value.';
                $lines[] = $this->commentDivider('-');
                foreach ($preCtasStatements as $stmt) {
                    $lines[] = $stmt;
                }
                $lines[] = '';
            }

            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Drop target table if it exists';
            $lines[] = $this->commentDivider('=');
            $lines[] = 'BEGIN';
            $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$target} CASCADE CONSTRAINTS PURGE';";
            $lines[] = 'EXCEPTION';
            $lines[] = '  WHEN OTHERS THEN';
            $lines[] = '    IF SQLCODE != -942 THEN';
            $lines[] = '      RAISE;';
            $lines[] = '    END IF;';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';

            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Create anonymized working copy';
            $lines[] = '-- NOTE:';
            $lines[] = '--  * Uses SELECT * to avoid invalid identifier failures';
            $lines[] = '--  * Copies only columns visible to this user';
            $lines[] = '--  * No constraints / indexes are copied (expected)';
            if (is_array($longColumns) && $longColumns !== []) {
                $lines[] = '--  * LONG columns are omitted to avoid ORA-00997';
            }
            $lines[] = $this->commentDivider('=');

            if (! is_string($selectList) || trim($selectList) === '') {
                $lines[] = '-- Skipped: no non-LONG columns available for CTAS.';
            } elseif (trim($selectList) === '*' && (empty($longColumns))) {
                $lines[] = 'CREATE TABLE ' . $target . ' AS';
                $lines[] = 'SELECT *';
                $fromLines = $this->buildCloneFromLines($source, $sourceAlias, $postSourceJoins);
                $lines = array_merge($lines, $fromLines);
                $lines[count($lines) - 1] .= ';';
            } else {
                $lines[] = 'CREATE TABLE ' . $target . ' AS';
                $lines[] = 'SELECT ' . $selectList;
                $fromLines = $this->buildCloneFromLines($source, $sourceAlias, $postSourceJoins);
                $lines = array_merge($lines, $fromLines);
                $lines[count($lines) - 1] .= ';';
            }
            $lines[] = $this->commentDivider('=');
            $lines[] = '';

            // Emit deferred (post-CTAS) statements for this table (e.g. MERGE-based shuffles).
            $deferred = $mapping['deferred_statements'] ?? [];
            if ($deferred !== []) {
                $lines[] = $this->commentDivider('-');
                $lines[] = '-- Post-CTAS operations for ' . $target;
                $lines[] = '-- These methods cannot be expressed as inline SELECT expressions.';
                $lines[] = $this->commentDivider('-');
                foreach ($deferred as $stmt) {
                    $lines[] = $stmt;
                    $lines[] = '';
                }
            }
        }

        return $lines;
    }

    protected function buildCloneFromLines(string $source, ?string $sourceAlias, array $postSourceJoins = []): array
    {
        $lines = ['FROM   ' . $source . ($sourceAlias ? (' ' . $sourceAlias) : '')];

        foreach ($postSourceJoins as $joinClause) {
            if (! is_string($joinClause) || trim($joinClause) === '') {
                continue;
            }

            $lines[] = '       ' . trim($joinClause);
        }

        return $lines;
    }

    protected function buildSourceAccessPreflightForClones(array $rewriteContext): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];
        $targetSchema = trim((string) ($rewriteContext['target_schema'] ?? ''));

        if (! is_array($tablesById) || $tablesById === [] || $targetSchema === '') {
            return [];
        }

        $resolveSourcesForTableIds = function (array $tableIds) use ($tablesById): array {
            return collect($tableIds)
                ->map(function (int $tableId) use ($tablesById) {
                    $mapping = $tablesById[$tableId] ?? null;
                    return is_array($mapping) ? ($mapping['source_qualified'] ?? null) : null;
                })
                ->filter(fn($source) => is_string($source) && trim($source) !== '')
                ->map(fn(string $source) => trim($source))
                ->unique()
                ->values()
                ->all();
        };

        $grantScopeTableIds = array_values(array_filter(
            array_map('intval', $rewriteContext['grant_scope_table_ids'] ?? []),
            fn(int $id) => $id > 0
        ));

        $sources = [];
        if ($grantScopeTableIds !== []) {
            $sources = $resolveSourcesForTableIds($grantScopeTableIds);
        }

        if ($sources === []) {
            $allTableIds = array_values(array_filter(array_map('intval', array_keys($tablesById)), fn(int $id) => $id > 0));
            $sources = $resolveSourcesForTableIds($allTableIds);
        }

        if ($sources === []) {
            return [];
        }

        $targetSchemaUpper = Str::upper($targetSchema);
        $targetSchemaLiteral = str_replace("'", "''", $targetSchemaUpper);

        // Collect distinct source schemas for fallback guidance.
        $sourceSchemas = collect($sources)
            ->map(fn($s) => explode('.', $s, 2)[0] ?? '')
            ->filter(fn($s) => $s !== '')
            ->map(fn($s) => Str::upper($s))
            ->unique()
            ->values()
            ->all();

        $sourceSchemaSample = $sourceSchemas[0] ?? 'SOURCE_OWNER';
        $sourceCount = count($sources);

        $needsCreateView = false;
        $needsCreateTable = false;
        foreach ($tablesById as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            $relationKind = $this->normalizeRelationKind($mapping['target_relation_kind'] ?? ($rewriteContext['target_relation_kind'] ?? 'table'));
            if ($relationKind === 'view') {
                $needsCreateView = true;
            } else {
                $needsCreateTable = true;
            }
        }

        // ── Section header ────────────────────────────────────────────
        $lines = [
            $this->commentDivider('='),
            '-- Privilege preflight (optimistic)',
            '-- Attempts all privilege setup as the current user.',
            '-- If the current user cannot grant or create objects, the script',
            '-- stops with a clear message explaining what to do.',
            $this->commentDivider('='),
            '',
        ];

        // ── Step 1: Try SELECT grants (best-effort) ──────────────────
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- Step 1: Ensure SELECT grants on source objects to target schema';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'DECLARE';
        $lines[] = '  v_current_user   VARCHAR2(128) := UPPER(USER);';
        $lines[] = "  v_target_schema  VARCHAR2(128) := '{$targetSchemaLiteral}';";
        $lines[] = '  v_grant_ok       NUMBER := 0;';
        $lines[] = '  v_grant_skip     NUMBER := 0;';
        $lines[] = 'BEGIN';

        foreach ($sources as $source) {
            $grantSql = "GRANT SELECT ON {$source} TO {$targetSchemaLiteral}";
            $sourceLiteral = str_replace("'", "''", $source);

            $lines[] = '  BEGIN';
            $lines[] = "    EXECUTE IMMEDIATE '" . str_replace("'", "''", $grantSql) . "';";
            $lines[] = '    v_grant_ok := v_grant_ok + 1;';
            $lines[] = '  EXCEPTION';
            $lines[] = '    WHEN OTHERS THEN';
            $lines[] = '      v_grant_skip := v_grant_skip + 1;';
            $lines[] = '  END;';
        }

        $lines[] = '';
        if ($needsCreateView || $needsCreateTable) {
            $lines[] = '  -- Try system privilege grants needed for target object creation';
        }
        if ($needsCreateView) {
            $lines[] = '  BEGIN';
            $lines[] = "    EXECUTE IMMEDIATE 'GRANT CREATE VIEW TO {$targetSchemaLiteral}';";
            $lines[] = '  EXCEPTION WHEN OTHERS THEN NULL;';
            $lines[] = '  END;';
        }
        if ($needsCreateTable) {
            $lines[] = '  BEGIN';
            $lines[] = "    EXECUTE IMMEDIATE 'GRANT CREATE TABLE TO {$targetSchemaLiteral}';";
            $lines[] = '  EXCEPTION WHEN OTHERS THEN NULL;';
            $lines[] = '  END;';
        }
        $lines[] = '';
        $lines[] = "  IF v_grant_ok > 0 THEN";
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Step 1: granted SELECT on ' || v_grant_ok || ' object(s) to ' || v_target_schema || '.');";
        $lines[] = "  ELSIF v_grant_skip = {$sourceCount} THEN";
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Step 1: no grant privilege — assuming grants are pre-configured.');";
        $lines[] = '  END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = '';

        // ── Step 2: Validate SELECT grants exist ─────────────────────
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- Step 2: Validate SELECT grants exist for target schema';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'DECLARE';
        $lines[] = '  v_failures      NUMBER := 0;';
        $lines[] = '  v_grant_count   NUMBER := 0;';
        $lines[] = "  v_target_schema VARCHAR2(128) := '{$targetSchemaLiteral}';";
        $lines[] = '  v_current_user  VARCHAR2(128) := UPPER(USER);';
        $lines[] = 'BEGIN';

        foreach ($sources as $index => $source) {
            $sourceLiteral = str_replace("'", "''", $source);

            $ownerPart = '';
            $objectPart = '';
            $parts = explode('.', $source, 2);
            if (count($parts) === 2) {
                $ownerPart = Str::upper(trim($parts[0]));
                $objectPart = Str::upper(trim($parts[1]));
            } else {
                $objectPart = Str::upper(trim($source));
            }

            $ownerLiteral = str_replace("'", "''", $ownerPart);
            $objectLiteral = str_replace("'", "''", $objectPart);

            $lines[] = '  v_grant_count := 0;';
            $lines[] = '  BEGIN';
            if ($ownerPart !== '') {
                $lines[] = "    EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ALL_TAB_PRIVS WHERE OWNER = :1 AND TABLE_NAME = :2 AND GRANTEE = :3 AND PRIVILEGE = ''SELECT''' INTO v_grant_count USING '{$ownerLiteral}', '{$objectLiteral}', v_target_schema;";
                $lines[] = '  EXCEPTION WHEN OTHERS THEN';
                $lines[] = '    BEGIN';
                $lines[] = "      EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ALL_TAB_PRIVS WHERE TABLE_SCHEMA = :1 AND TABLE_NAME = :2 AND GRANTEE = :3 AND PRIVILEGE = ''SELECT''' INTO v_grant_count USING '{$ownerLiteral}', '{$objectLiteral}', v_target_schema;";
                $lines[] = '    EXCEPTION WHEN OTHERS THEN v_grant_count := 0;';
                $lines[] = '    END;';
            } else {
                $lines[] = "    EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ALL_TAB_PRIVS WHERE TABLE_NAME = :1 AND GRANTEE = :2 AND PRIVILEGE = ''SELECT''' INTO v_grant_count USING '{$objectLiteral}', v_target_schema;";
                $lines[] = '  EXCEPTION WHEN OTHERS THEN v_grant_count := 0;';
            }
            $lines[] = '  END;';
            // If ALL_TAB_PRIVS didn't find it (e.g. running as source owner), try USER_TAB_PRIVS_MADE.
            $lines[] = '  IF v_grant_count = 0 THEN';
            $lines[] = '    BEGIN';
            $lines[] = '      SELECT COUNT(*) INTO v_grant_count FROM USER_TAB_PRIVS_MADE';
            $lines[] = "      WHERE TABLE_NAME = '{$objectLiteral}' AND GRANTEE = v_target_schema AND PRIVILEGE = 'SELECT';";
            $lines[] = '    EXCEPTION WHEN OTHERS THEN v_grant_count := 0;';
            $lines[] = '    END;';
            $lines[] = '  END IF;';
            $lines[] = '  IF v_grant_count = 0 THEN';
            $lines[] = '    v_failures := v_failures + 1;';
            $lines[] = "    DBMS_OUTPUT.PUT_LINE('  Missing grant: SELECT ON {$sourceLiteral} TO ' || v_target_schema);";
            $lines[] = '  END IF;';
        }

        $lines[] = '';
        $lines[] = '  IF v_failures > 0 THEN';
        $lines[] = "    RAISE_APPLICATION_ERROR(-20042, v_failures || ' source object(s) lack SELECT grants to ' || v_target_schema || '. Connect as " . str_replace("'", "''", $sourceSchemaSample) . " (source owner) and re-run, or ask a DBA to grant them.');";
        $lines[] = '  ELSE';
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Step 2: all {$sourceCount} source object grants verified.');";
        $lines[] = '  END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = '';

        // ── Step 3: DDL readiness ─────────────────────────────────────
        $lines[] = $this->commentDivider('-');
        $lines[] = '-- Step 3: Verify DDL privileges for target schema';
        $lines[] = $this->commentDivider('-');
        $lines[] = 'DECLARE';
        $lines[] = '  v_current_user  VARCHAR2(128) := UPPER(USER);';
        $lines[] = "  v_target_schema VARCHAR2(128) := '{$targetSchemaLiteral}';";
        $lines[] = '  v_needs_view    NUMBER := ' . ($needsCreateView ? '1' : '0') . ';';
        $lines[] = '  v_needs_table   NUMBER := ' . ($needsCreateTable ? '1' : '0') . ';';
        $lines[] = '  v_can_create_view   NUMBER := 0;';
        $lines[] = '  v_can_create_table  NUMBER := 0;';
        $lines[] = 'BEGIN';
        $lines[] = '  BEGIN';
        $lines[] = '    SELECT COUNT(*) INTO v_can_create_view FROM SESSION_PRIVS';
        $lines[] = "    WHERE PRIVILEGE IN ('CREATE VIEW', 'CREATE ANY VIEW');";
        $lines[] = '  EXCEPTION WHEN OTHERS THEN v_can_create_view := 0;';
        $lines[] = '  END;';
        $lines[] = '  BEGIN';
        $lines[] = '    SELECT COUNT(*) INTO v_can_create_table FROM SESSION_PRIVS';
        $lines[] = "    WHERE PRIVILEGE IN ('CREATE TABLE', 'CREATE ANY TABLE');";
        $lines[] = '  EXCEPTION WHEN OTHERS THEN v_can_create_table := 0;';
        $lines[] = '  END;';
        $lines[] = '';
        $lines[] = '  IF (v_needs_view = 0 OR v_can_create_view > 0)';
        $lines[] = '     AND (v_needs_table = 0 OR v_can_create_table > 0)';
        $lines[] = '     AND (v_current_user = v_target_schema OR (v_needs_view = 1 AND v_can_create_view > 0) OR (v_needs_table = 1 AND v_can_create_table > 0)) THEN';
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Step 3: DDL readiness confirmed (user=' || v_current_user || ').');";
        $lines[] = '  ELSE';
        $lines[] = '    IF v_current_user = v_target_schema AND v_needs_view = 1 AND v_can_create_view = 0 THEN';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20043, 'User ' || v_current_user || ' lacks CREATE VIEW. A DBA must run: GRANT CREATE VIEW TO ' || v_target_schema);";
        $lines[] = '    ELSIF v_current_user = v_target_schema AND v_needs_table = 1 AND v_can_create_table = 0 THEN';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20043, 'User ' || v_current_user || ' lacks CREATE TABLE. A DBA must run: GRANT CREATE TABLE TO ' || v_target_schema);";
        $lines[] = '    ELSIF v_needs_view = 1 AND v_needs_table = 1 THEN';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20043, 'User ' || v_current_user || ' cannot create objects in ' || v_target_schema || '. Connect as ' || v_target_schema || ' or grant CREATE ANY VIEW/CREATE ANY TABLE.');";
        $lines[] = '    ELSIF v_needs_view = 1 THEN';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20043, 'User ' || v_current_user || ' cannot create views in ' || v_target_schema || '. Connect as ' || v_target_schema || ' or grant CREATE ANY VIEW.');";
        $lines[] = '    ELSE';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20043, 'User ' || v_current_user || ' cannot create tables in ' || v_target_schema || '. Connect as ' || v_target_schema || ' or grant CREATE ANY TABLE.');";
        $lines[] = '    END IF;';
        $lines[] = '  END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function buildRequiredPackagePreflight(array $rewriteContext): array
    {
        $refs = $rewriteContext['required_package_refs'] ?? [];
        if (! is_array($refs) || $refs === []) {
            return [];
        }

        $refs = array_values(array_filter($refs, fn($ref) => is_array($ref) && ! empty($ref['owner']) && ! empty($ref['package'])));
        if ($refs === []) {
            return [];
        }

        usort($refs, function (array $a, array $b): int {
            return strcmp(($a['owner'] ?? '') . '.' . ($a['package'] ?? ''), ($b['owner'] ?? '') . '.' . ($b['package'] ?? ''));
        });

        $packageOwnerHint = str_replace("'", "''", $this->anonymizationPackageOwner());

        $lines = [
            $this->commentDivider('='),
            '-- Package readiness preflight',
            '-- Confirms required Faker lookup packages are pre-installed, valid, and executable by the runtime user.',
            '-- Install once as ' . $packageOwnerHint . ' using the package installation script (for example: database/seeders/anonymization/packages/ANON_DATA_INSTALL_ALL.sql).',
            $this->commentDivider('='),
            '',
            'DECLARE',
            '  v_missing NUMBER := 0;',
            '  v_exists  NUMBER := 0;',
            '  v_exec_count NUMBER := 0;',
            '  v_current_user VARCHAR2(128) := UPPER(USER);',
            '  v_pkg_status VARCHAR2(30);',
            '  v_body_status VARCHAR2(30);',
            'BEGIN',
        ];

        foreach ($refs as $ref) {
            $owner = str_replace("'", "''", $this->mapAnonymizationPackageOwner((string) $ref['owner']));
            $package = str_replace("'", "''", strtoupper((string) $ref['package']));

            $lines[] = '  v_exists := 0;';
            $lines[] = '  v_exec_count := 0;';
            $lines[] = '  v_pkg_status := NULL;';
            $lines[] = '  v_body_status := NULL;';
            $lines[] = '  BEGIN';
            $lines[] = '    SELECT COUNT(*) INTO v_exists FROM ALL_OBJECTS';
            $lines[] = "    WHERE OWNER = '{$owner}' AND OBJECT_NAME = '{$package}' AND OBJECT_TYPE = 'PACKAGE';";
            $lines[] = '  EXCEPTION WHEN OTHERS THEN v_exists := 0;';
            $lines[] = '  END;';
            $lines[] = '  IF v_exists = 0 THEN';
            $lines[] = '    v_missing := v_missing + 1;';
            $lines[] = "    DBMS_OUTPUT.PUT_LINE('  Missing package: {$owner}.{$package}');";
            $lines[] = '  END IF;';
            $lines[] = '  IF v_exists > 0 THEN';
            $lines[] = '    BEGIN';
            $lines[] = "      SELECT MAX(CASE WHEN OBJECT_TYPE = 'PACKAGE' THEN STATUS END),";
            $lines[] = "             MAX(CASE WHEN OBJECT_TYPE = 'PACKAGE BODY' THEN STATUS END)";
            $lines[] = '      INTO v_pkg_status, v_body_status';
            $lines[] = '      FROM ALL_OBJECTS';
            $lines[] = "      WHERE OWNER = '{$owner}' AND OBJECT_NAME = '{$package}'";
            $lines[] = "        AND OBJECT_TYPE IN ('PACKAGE', 'PACKAGE BODY');";
            $lines[] = '    EXCEPTION WHEN OTHERS THEN';
            $lines[] = '      v_pkg_status := NULL;';
            $lines[] = '      v_body_status := NULL;';
            $lines[] = '    END;';
            $lines[] = '    BEGIN';
            $lines[] = "      IF v_current_user = '{$owner}' THEN";
            $lines[] = '        v_exec_count := 1;';
            $lines[] = '      ELSE';
            $lines[] = '        BEGIN';
            $lines[] = "          EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ALL_TAB_PRIVS WHERE OWNER = :1 AND TABLE_NAME = :2 AND PRIVILEGE = ''EXECUTE'' AND (GRANTEE = :3 OR GRANTEE = ''PUBLIC'' OR GRANTEE IN (SELECT ROLE FROM SESSION_ROLES))' INTO v_exec_count USING '{$owner}', '{$package}', v_current_user;";
            $lines[] = '        EXCEPTION WHEN OTHERS THEN';
            $lines[] = '          BEGIN';
            $lines[] = "            EXECUTE IMMEDIATE 'SELECT COUNT(*) FROM ALL_TAB_PRIVS WHERE TABLE_SCHEMA = :1 AND TABLE_NAME = :2 AND PRIVILEGE = ''EXECUTE'' AND (GRANTEE = :3 OR GRANTEE = ''PUBLIC'' OR GRANTEE IN (SELECT ROLE FROM SESSION_ROLES))' INTO v_exec_count USING '{$owner}', '{$package}', v_current_user;";
            $lines[] = '          EXCEPTION WHEN OTHERS THEN v_exec_count := 0;';
            $lines[] = '          END;';
            $lines[] = '        END;';
            $lines[] = '        IF v_exec_count = 0 THEN';
            $lines[] = '          BEGIN';
            $lines[] = '            SELECT COUNT(*) INTO v_exec_count FROM USER_TAB_PRIVS_MADE';
            $lines[] = "            WHERE TABLE_NAME = '{$package}' AND PRIVILEGE = 'EXECUTE'";
            $lines[] = "              AND (GRANTEE = v_current_user OR GRANTEE = 'PUBLIC');";
            $lines[] = '          EXCEPTION WHEN OTHERS THEN v_exec_count := 0;';
            $lines[] = '          END;';
            $lines[] = '        END IF;';
            $lines[] = '      END IF;';
            $lines[] = '    EXCEPTION WHEN OTHERS THEN';
            $lines[] = '      v_exec_count := 0;';
            $lines[] = '    END;';
            $lines[] = "    IF NVL(v_pkg_status, 'INVALID') != 'VALID' THEN";
            $lines[] = '      v_missing := v_missing + 1;';
            $lines[] = "      DBMS_OUTPUT.PUT_LINE('  Invalid package spec: {$owner}.{$package} (status=' || NVL(v_pkg_status, 'UNKNOWN') || ')');";
            $lines[] = '    END IF;';
            $lines[] = "    IF NVL(v_body_status, 'INVALID') != 'VALID' THEN";
            $lines[] = '      v_missing := v_missing + 1;';
            $lines[] = "      DBMS_OUTPUT.PUT_LINE('  Invalid package body: {$owner}.{$package} (status=' || NVL(v_body_status, 'UNKNOWN') || ')');";
            $lines[] = '    END IF;';
            $lines[] = '    IF v_exec_count = 0 THEN';
            $lines[] = '      v_missing := v_missing + 1;';
            $lines[] = "      DBMS_OUTPUT.PUT_LINE('  Missing EXECUTE grant for runtime user on {$owner}.{$package} (grant to user, role, or PUBLIC).');";
            $lines[] = "      DBMS_OUTPUT.PUT_LINE('    Suggested: GRANT EXECUTE ON {$owner}.{$package} TO ' || v_current_user || ';');";
            $lines[] = '    END IF;';
            $lines[] = '  END IF;';
        }

        $lines[] = '  IF v_missing > 0 THEN';
        $lines[] = "    RAISE_APPLICATION_ERROR(-20044, v_missing || ' package readiness issue(s) detected. Install/recompile {$packageOwnerHint} packages, then re-run this job script.');";
        $lines[] = '  ELSE';
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Package preflight: all required packages and bodies are VALID.');";
        $lines[] = '  END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function buildConditionalPackageBootstrap(array $rewriteContext): array
    {
        $refs = $rewriteContext['required_package_refs'] ?? [];
        if (! is_array($refs) || $refs === []) {
            return [];
        }

        $refs = array_values(array_filter($refs, fn($ref) => is_array($ref) && ! empty($ref['owner']) && ! empty($ref['package'])));
        if ($refs === []) {
            return [];
        }

        $packageNames = array_values(array_unique(array_map(
            fn($ref) => strtoupper((string) ($ref['package'] ?? '')),
            $refs
        )));

        $packages = AnonymizationPackage::query()
            ->withTrashed()
            ->whereIn('package_name', $packageNames)
            ->get()
            ->keyBy(fn(AnonymizationPackage $pkg) => strtoupper((string) ($pkg->package_name ?? '')));

        $lines = [
            $this->commentDivider('='),
            '-- Conditional package bootstrap',
            '-- Installs required Faker packages only when missing in target schema.',
            '-- Uses package artifacts stored in KLAMM metadata.',
            $this->commentDivider('='),
            '',
        ];

        $emitted = 0;

        foreach ($refs as $ref) {
            $owner = $this->mapAnonymizationPackageOwner((string) ($ref['owner'] ?? ''));
            $packageName = strtoupper((string) ($ref['package'] ?? ''));

            if ($owner === '' || $packageName === '') {
                continue;
            }

            $package = $packages->get($packageName);
            $specSql = $this->rewriteAnonymizationPackageOwner(trim((string) ($package?->package_spec_sql ?? '')));
            $bodySql = $this->rewriteAnonymizationPackageOwner(trim((string) ($package?->package_body_sql ?? '')));

            if ($specSql === '' || $bodySql === '') {
                $lines[] = '-- Package bootstrap unavailable for ' . $owner . '.' . $packageName . ' (missing stored spec/body SQL).';
                continue;
            }

            $lines = array_merge($lines, $this->buildConditionalPackageInstallBlock(
                $owner,
                $packageName,
                $specSql,
                $bodySql
            ));
            $emitted++;
        }

        if ($emitted === 0) {
            $lines[] = '-- No installable package payloads available; preflight checks will enforce readiness.';
        }

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        return $lines;
    }

    protected function buildConditionalPackageInstallBlock(
        string $owner,
        string $packageName,
        string $specSql,
        string $bodySql
    ): array {
        $ownerLiteral = str_replace("'", "''", strtoupper($owner));
        $packageLiteral = str_replace("'", "''", strtoupper($packageName));

        $lines = [
            '-- Package bootstrap candidate: ' . $ownerLiteral . '.' . $packageLiteral,
            'DECLARE',
            '  v_pkg_status VARCHAR2(30) := NULL;',
            '  v_body_status VARCHAR2(30) := NULL;',
            '  v_needs_install NUMBER := 0;',
            '  v_cursor INTEGER := NULL;',
            '  v_lines DBMS_SQL.VARCHAR2A;',
            '  v_line_count PLS_INTEGER := 0;',
            'BEGIN',
            '  BEGIN',
            "    SELECT MAX(CASE WHEN OBJECT_TYPE = 'PACKAGE' THEN STATUS END),",
            "           MAX(CASE WHEN OBJECT_TYPE = 'PACKAGE BODY' THEN STATUS END)",
            '      INTO v_pkg_status, v_body_status',
            '      FROM ALL_OBJECTS',
            "     WHERE OWNER = '{$ownerLiteral}'",
            "       AND OBJECT_NAME = '{$packageLiteral}'",
            "       AND OBJECT_TYPE IN ('PACKAGE', 'PACKAGE BODY');",
            '  EXCEPTION WHEN OTHERS THEN',
            '    v_pkg_status := NULL;',
            '    v_body_status := NULL;',
            '  END;',
            "  IF NVL(v_pkg_status, 'INVALID') != 'VALID' OR NVL(v_body_status, 'INVALID') != 'VALID' THEN",
            '    v_needs_install := 1;',
            "    DBMS_OUTPUT.PUT_LINE('Package status before bootstrap: spec=' || NVL(v_pkg_status, 'MISSING') || ', body=' || NVL(v_body_status, 'MISSING'));",
            '  END IF;',
            '  IF v_needs_install = 1 THEN',
            "    DBMS_OUTPUT.PUT_LINE('Installing missing package {$ownerLiteral}.{$packageLiteral} ...');",
        ];

        $lines = array_merge($lines, $this->renderPlsqlExecuteChunkedStatement('v_lines', 'v_line_count', 'v_cursor', trim($specSql)));
        $lines = array_merge($lines, $this->renderPlsqlExecuteChunkedStatement('v_lines', 'v_line_count', 'v_cursor', trim($bodySql)));

        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Installed package {$ownerLiteral}.{$packageLiteral}.');";
        $lines[] = '  ELSE';
        $lines[] = "    DBMS_OUTPUT.PUT_LINE('Package {$ownerLiteral}.{$packageLiteral} already installed and VALID; skipping bootstrap.');";
        $lines[] = '  END IF;';
        $lines[] = 'EXCEPTION';
        $lines[] = '  WHEN OTHERS THEN';
        $lines[] = '    IF v_cursor IS NOT NULL AND DBMS_SQL.IS_OPEN(v_cursor) THEN';
        $lines[] = '      DBMS_SQL.CLOSE_CURSOR(v_cursor);';
        $lines[] = '    END IF;';
        $lines[] = '    IF SQLCODE = -1031 THEN';
        $lines[] = "      DBMS_OUTPUT.PUT_LINE('Insufficient privileges to install package {$ownerLiteral}.{$packageLiteral}; skipping bootstrap.');";
        $lines[] = "      DBMS_OUTPUT.PUT_LINE('Install package payloads once as {$ownerLiteral} (or a DBA), then re-run this script.');";
        $lines[] = '    ELSIF SQLCODE = -24344 THEN';
        $lines[] = "      DBMS_OUTPUT.PUT_LINE('Package {$ownerLiteral}.{$packageLiteral} compiled with errors.');";
        $lines[] = "      DBMS_OUTPUT.PUT_LINE('Compiler diagnostics:');";
        $lines[] = '      DECLARE';
        $lines[] = '        v_found NUMBER := 0;';
        $lines[] = '      BEGIN';
        $lines[] = '        BEGIN';
        $lines[] = '          FOR rec IN (';
        $lines[] = '            SELECT TYPE, LINE, POSITION, TEXT';
        $lines[] = '              FROM ALL_ERRORS';
        $lines[] = "             WHERE OWNER = '{$ownerLiteral}'";
        $lines[] = "               AND NAME = '{$packageLiteral}'";
        $lines[] = "               AND TYPE IN ('PACKAGE', 'PACKAGE BODY')";
        $lines[] = "             ORDER BY CASE TYPE WHEN 'PACKAGE' THEN 1 ELSE 2 END, LINE, POSITION";
        $lines[] = '          ) LOOP';
        $lines[] = '            v_found := v_found + 1;';
        $lines[] = "            DBMS_OUTPUT.PUT_LINE('  ' || rec.TYPE || ' L' || rec.LINE || ':' || rec.POSITION || ' ' || rec.TEXT);";
        $lines[] = '          END LOOP;';
        $lines[] = '        EXCEPTION';
        $lines[] = '          WHEN OTHERS THEN';
        $lines[] = '            NULL;';
        $lines[] = '        END;';
        $lines[] = '        IF v_found = 0 THEN';
        $lines[] = '          BEGIN';
        $lines[] = '            FOR rec IN (';
        $lines[] = '              SELECT TYPE, LINE, POSITION, TEXT';
        $lines[] = '                FROM USER_ERRORS';
        $lines[] = "               WHERE NAME = '{$packageLiteral}'";
        $lines[] = "                 AND TYPE IN ('PACKAGE', 'PACKAGE BODY')";
        $lines[] = "               ORDER BY CASE TYPE WHEN 'PACKAGE' THEN 1 ELSE 2 END, LINE, POSITION";
        $lines[] = '            ) LOOP';
        $lines[] = '              v_found := v_found + 1;';
        $lines[] = "              DBMS_OUTPUT.PUT_LINE('  ' || rec.TYPE || ' L' || rec.LINE || ':' || rec.POSITION || ' ' || rec.TEXT);";
        $lines[] = '            END LOOP;';
        $lines[] = '          EXCEPTION';
        $lines[] = '            WHEN OTHERS THEN';
        $lines[] = '              NULL;';
        $lines[] = '          END;';
        $lines[] = '        END IF;';
        $lines[] = '        IF v_found = 0 THEN';
        $lines[] = "          DBMS_OUTPUT.PUT_LINE('  (No compiler diagnostics visible. Query ALL_ERRORS/USER_ERRORS for details.)');";
        $lines[] = '        END IF;';
        $lines[] = '      END;';
        $lines[] = "      RAISE_APPLICATION_ERROR(-20045, 'Package {$ownerLiteral}.{$packageLiteral} failed to compile. See DBMS_OUTPUT compiler diagnostics above.');";
        $lines[] = '    ELSE';
        $lines[] = '      RAISE;';
        $lines[] = '    END IF;';
        $lines[] = 'END;';
        $lines[] = '/';
        $lines[] = '';

        return $lines;
    }

    protected function renderPlsqlExecuteClobStatement(string $sqlVar, string $cursorVar, string $statement): array
    {
        return $this->renderPlsqlExecuteChunkedStatement('v_lines', 'v_line_count', $cursorVar, $statement);
    }

    protected function renderPlsqlExecuteChunkedStatement(
        string $linesVar,
        string $countVar,
        string $cursorVar,
        string $statement
    ): array {
        $statement = $this->normalizeStatementForDbmsSqlParse($statement);

        $maxChunkBytes = 20000;
        $chunks = [];

        if ($statement !== '') {
            $linesForChunking = preg_split('/\n/', $statement) ?: [];
            $currentChunk = '';

            foreach ($linesForChunking as $line) {
                $lineWithNewline = $line . "\n";

                if ($currentChunk !== '' && strlen($currentChunk) + strlen($lineWithNewline) > $maxChunkBytes) {
                    $chunks[] = rtrim($currentChunk, "\n");
                    $currentChunk = '';
                }

                if (strlen($lineWithNewline) > $maxChunkBytes) {
                    $lineChunks = str_split($lineWithNewline, $maxChunkBytes);
                    foreach ($lineChunks as $lineChunk) {
                        if ($lineChunk === '') {
                            continue;
                        }

                        if (strlen($lineChunk) === $maxChunkBytes) {
                            $chunks[] = $lineChunk;
                        } else {
                            $currentChunk = $lineChunk;
                        }
                    }

                    continue;
                }

                $currentChunk .= $lineWithNewline;
            }

            if ($currentChunk !== '') {
                $chunks[] = rtrim($currentChunk, "\n");
            }
        }

        if ($chunks === []) {
            $chunks = [''];
        }

        $lines = [];
        $lines[] = '    ' . $linesVar . '.DELETE;';
        $lines[] = '    ' . $countVar . ' := 0;';

        foreach ($chunks as $index => $chunk) {
            $literal = $this->oracleQQuotedLiteral($chunk);
            $lineNo = $index + 1;
            $lines[] = '    ' . $countVar . ' := ' . $countVar . ' + 1;';
            $lines[] = '    ' . $linesVar . '(' . $lineNo . ') := ' . $literal . ';';
        }

        $lines[] = '    ' . $cursorVar . ' := DBMS_SQL.OPEN_CURSOR;';
        $lines[] = '    DBMS_SQL.PARSE(' . $cursorVar . ', ' . $linesVar . ', 1, ' . $countVar . ', TRUE, DBMS_SQL.NATIVE);';
        $lines[] = '    DBMS_SQL.CLOSE_CURSOR(' . $cursorVar . ');';

        return $lines;
    }

    protected function normalizeStatementForDbmsSqlParse(string $statement): string
    {
        if ($statement === '') {
            return '';
        }

        $statement = str_replace("\r\n", "\n", $statement);
        $statement = str_replace("\r", "\n", $statement);

        $lines = preg_split('/\n/', $statement) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            if (trim($line) === '/') {
                continue;
            }

            $filtered[] = rtrim($line);
        }

        return trim(implode("\n", $filtered));
    }

    protected function oracleQQuotedLiteral(string $value): string
    {
        foreach (['~', '!', '#', '|', '^', '%', '@'] as $delimiter) {
            if (! str_contains($value, $delimiter)) {
                return "q'{$delimiter}{$value}{$delimiter}'";
            }
        }

        return "'" . str_replace("'", "''", $value) . "'";
    }

    protected function renderJobTableClonesForTables(array $rewriteContext, array $tableIds): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];

        if (! is_array($tablesById) || $tablesById === [] || $tableIds === []) {
            return [];
        }

        $subset = [];
        foreach ($tableIds as $tableId) {
            $tableId = (int) $tableId;
            if ($tableId <= 0) {
                continue;
            }
            if (isset($tablesById[$tableId])) {
                $subset[$tableId] = $tablesById[$tableId];
            }
        }

        if ($subset === []) {
            return [];
        }

        $context = $rewriteContext;
        $context['tables_by_id'] = $subset;

        return $this->renderJobTableClones($context);
    }

    protected function orderTableIdsByDependencies(Collection $columns): array
    {
        $tableIds = [];
        $edges = [];
        $inDegree = [];

        foreach ($columns as $column) {
            $tableId = (int) ($column->table_id ?? 0);
            if ($tableId > 0) {
                $tableIds[$tableId] = true;
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTableId = (int) ($parent->table_id ?? 0);
                if ($parentTableId > 0) {
                    $tableIds[$parentTableId] = true;
                }

                if ($tableId > 0 && $parentTableId > 0 && $tableId !== $parentTableId) {
                    $edges[$parentTableId][$tableId] = true;
                }
            }
        }

        $tableIds = array_keys($tableIds);
        sort($tableIds);

        foreach ($tableIds as $id) {
            $inDegree[$id] = 0;
        }

        foreach ($edges as $from => $targets) {
            foreach (array_keys($targets) as $to) {
                if (! array_key_exists($to, $inDegree)) {
                    $inDegree[$to] = 0;
                }
                $inDegree[$to]++;
            }
        }

        $queue = [];
        foreach ($inDegree as $id => $count) {
            if ($count === 0) {
                $queue[] = $id;
            }
        }

        $ordered = [];
        while ($queue !== []) {
            $current = array_shift($queue);
            $ordered[] = $current;

            foreach (array_keys($edges[$current] ?? []) as $to) {
                $inDegree[$to]--;
                if ($inDegree[$to] === 0) {
                    $queue[] = $to;
                }
            }
        }

        if (count($ordered) < count($tableIds)) {
            $remaining = array_diff($tableIds, $ordered);
            sort($remaining);
            $ordered = array_merge($ordered, $remaining);
        }

        return $ordered;
    }

    protected function columnsByTableWithTypes(Collection $tables): array
    {
        $tableIds = $tables
            ->map(fn(AnonymousSiebelTable $table) => (int) $table->getKey())
            ->filter(fn(int $id) => $id > 0)
            ->values()
            ->all();

        if ($tableIds === []) {
            return [];
        }

        // Chunk the query to avoid enormous WHERE IN clauses on large schemas.
        $result = [];

        foreach (array_chunk($tableIds, self::TABLE_COLUMN_CHUNK_SIZE * 5) as $chunk) {
            $loaded = AnonymousSiebelColumn::query()
                ->with(['dataType'])
                ->whereIn('table_id', $chunk)
                ->get()
                ->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

            foreach ($loaded as $tableId => $columns) {
                $result[$tableId] = $columns;
            }
        }

        return $result;
    }

    protected function longColumnsForTable(Collection $columns): array
    {
        if ($columns->isEmpty()) {
            return [];
        }

        return $columns
            ->filter(fn(AnonymousSiebelColumn $column) => $this->isLongColumn($column))
            ->map(fn(AnonymousSiebelColumn $column) => (string) ($column->column_name ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function buildCloneSelectList(Collection $columns): string
    {
        if ($columns->isEmpty()) {
            return '*';
        }

        $hasLong = $columns->contains(fn(AnonymousSiebelColumn $column) => $this->isLongColumn($column));
        if (! $hasLong) {
            return '*';
        }

        $names = $columns
            ->filter(fn(AnonymousSiebelColumn $column) => ! $this->isLongColumn($column))
            ->map(fn(AnonymousSiebelColumn $column) => (string) ($column->column_name ?? ''))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($names === []) {
            return '';
        }

        return implode(', ', $names);
    }

    protected function isLongColumn(AnonymousSiebelColumn $column): bool
    {
        $typeName = strtolower(trim((string) ($column->getRelationValue('dataType')?->data_type_name ?? '')));

        if ($typeName === '') {
            $typeName = strtolower(trim((string) ($column->data_type ?? '')));
        }

        return $typeName !== '' && str_contains($typeName, 'long');
    }

    protected function buildForeignKeyStatements(array $rewriteContext): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];

        if (! is_array($tablesById) || $tablesById === []) {
            return [];
        }

        $tableIds = array_values(array_filter(array_map('intval', array_keys($tablesById)), fn($id) => $id > 0));
        if ($tableIds === []) {
            return [];
        }

        $tablesByIdentity = [];
        foreach ($tablesById as $tableId => $mapping) {
            if (($mapping['target_relation_kind'] ?? 'table') === 'view') {
                continue;
            }
            $schema = strtoupper(trim((string) ($mapping['source_schema'] ?? '')));
            $table = strtoupper(trim((string) ($mapping['source_table'] ?? '')));
            if ($schema !== '' && $table !== '') {
                $tablesByIdentity[$schema . '|' . $table] = (int) $tableId;
            }
        }

        // Pre-load only ROW_ID columns for parent verification (tiny result set: ≤1 per table).
        // This avoids loading ALL columns just for the parent compatibility check,
        // and eliminates cross-chunk lookup issues.
        $rowIdByTable = [];
        foreach (array_chunk($tableIds, self::TABLE_COLUMN_CHUNK_SIZE * 5) as $chunk) {
            $loaded = AnonymousSiebelColumn::query()
                ->with(['dataType'])
                ->whereIn('table_id', $chunk)
                ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
                ->get();

            foreach ($loaded as $col) {
                $rowIdByTable[(int) $col->table_id] = $col;
            }
        }

        $lines = [];
        $seen = [];

        // Process tables in chunks to keep memory bounded on large schema jobs.
        foreach (array_chunk($tableIds, self::TABLE_COLUMN_CHUNK_SIZE) as $tableChunk) {
            $columns = AnonymousSiebelColumn::query()
                ->with(['dataType', 'table.schema'])
                ->whereIn('table_id', $tableChunk)
                ->get();

            if ($columns->isEmpty()) {
                continue;
            }

            foreach ($columns as $column) {
                $childTableId = (int) ($column->table_id ?? 0);
                if ($childTableId <= 0) {
                    continue;
                }

                $childMap = $tablesById[$childTableId] ?? null;
                if (! is_array($childMap)) {
                    continue;
                }

                if (($childMap['target_relation_kind'] ?? 'table') === 'view') {
                    continue;
                }

                $childColumn = trim((string) ($column->column_name ?? ''));
                if ($childColumn === '') {
                    continue;
                }

                $childSelectedColumns = array_map('strtoupper', $childMap['selected_source_columns'] ?? []);
                $childNullUnselectedColumns = (bool) ($childMap['null_unselected_columns'] ?? false);
                if ($childNullUnselectedColumns && ! in_array(strtoupper($childColumn), $childSelectedColumns, true)) {
                    continue;
                }

                if ($this->isLongColumn($column)) {
                    continue;
                }

                $relationships = $this->resolveForeignKeyRelationships($column);
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

                    $parentMap = $tablesById[$parentTableId] ?? null;
                    if (! is_array($parentMap)) {
                        continue;
                    }

                    if (($parentMap['target_relation_kind'] ?? 'table') === 'view') {
                        continue;
                    }

                    $parentSelectedColumns = array_map('strtoupper', $parentMap['selected_source_columns'] ?? []);
                    $parentNullUnselectedColumns = (bool) ($parentMap['null_unselected_columns'] ?? false);
                    if ($parentNullUnselectedColumns && ! in_array('ROW_ID', $parentSelectedColumns, true)) {
                        continue;
                    }

                    // Use pre-loaded ROW_ID map for parent verification.
                    $parentModel = $rowIdByTable[$parentTableId] ?? null;

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

                    $lines[] = 'BEGIN';
                    $lines[] = "  EXECUTE IMMEDIATE 'ALTER TABLE {$childTarget} ADD CONSTRAINT {$constraintName} FOREIGN KEY ({$childColumn}) REFERENCES {$parentTarget} ({$parentColumn}) ENABLE NOVALIDATE';";
                    $lines[] = 'EXCEPTION';
                    $lines[] = '  WHEN OTHERS THEN';
                    $lines[] = '    IF SQLCODE NOT IN (-2275, -2298, -2261, -955) THEN';
                    $lines[] = '      RAISE;';
                    $lines[] = '    END IF;';
                    $lines[] = 'END;';
                    $lines[] = '/';
                    $lines[] = '';
                }
            }

            // Release column models for this chunk before loading the next.
            unset($columns);
        }

        return $lines;
    }

    protected function buildPrimaryKeyStatements(array $rewriteContext): array
    {
        $tablesById = $rewriteContext['tables_by_id'] ?? [];

        if (! is_array($tablesById) || $tablesById === []) {
            return [];
        }

        $tableIds = array_values(array_filter(array_map('intval', array_keys($tablesById)), fn($id) => $id > 0));
        if ($tableIds === []) {
            return [];
        }

        // Only load ROW_ID columns — that is all PK generation needs.
        // This avoids loading the entire column set for every table in scope.
        $columnsByTable = collect();

        foreach (array_chunk($tableIds, self::TABLE_COLUMN_CHUNK_SIZE * 5) as $chunk) {
            $loaded = AnonymousSiebelColumn::query()
                ->with(['dataType'])
                ->whereIn('table_id', $chunk)
                ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
                ->get()
                ->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

            $columnsByTable = $columnsByTable->merge($loaded);
        }

        $lines = [];
        $seen = [];

        foreach ($tablesById as $tableId => $mapping) {
            if (($mapping['target_relation_kind'] ?? 'table') === 'view') {
                continue;
            }
            $target = $mapping['target_qualified'] ?? null;
            if (! $target) {
                continue;
            }

            $selectedSourceColumns = array_map('strtoupper', $mapping['selected_source_columns'] ?? []);
            $nullUnselectedColumns = (bool) ($mapping['null_unselected_columns'] ?? false);
            if ($nullUnselectedColumns && ! in_array('ROW_ID', $selectedSourceColumns, true)) {
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
            $lines[] = 'BEGIN';
            $lines[] = "  EXECUTE IMMEDIATE 'ALTER TABLE {$target} ADD CONSTRAINT {$constraintName} PRIMARY KEY (ROW_ID)';";
            $lines[] = 'EXCEPTION';
            $lines[] = '  WHEN OTHERS THEN';
            $lines[] = '    IF SQLCODE NOT IN (-2260, -955) THEN';
            $lines[] = '      RAISE;';
            $lines[] = '    END IF;';
            $lines[] = 'END;';
            $lines[] = '/';
            $lines[] = '';
        }

        return $lines;
    }

    protected function resolveForeignKeyRelationships(AnonymousSiebelColumn $column): array
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

    protected function parseRelatedColumnsRaw(string $raw, AnonymousSiebelColumn $column): array
    {
        $parts = preg_split('/\s*;\s*/', $raw) ?: [];
        $relationships = [];

        $schema = $column->getRelationValue('table')?->getRelationValue('schema')?->schema_name;
        $schema = $schema ? (string) $schema : '';

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^([^.\s]+)\.([^.\s]+)\.([^\s]+)(?:\s+via\s+\S+)?$/i', $part, $matches)) {
                $relationships[] = [
                    'direction' => 'OUTBOUND',
                    'schema' => $matches[1],
                    'table' => $matches[2],
                    'column' => trim($matches[3], ','),
                ];
                continue;
            }

            $relationships[] = [
                'direction' => 'OUTBOUND',
                'schema' => $schema,
                'table' => $part,
                'column' => 'ROW_ID',
            ];
        }

        return $relationships;
    }

    protected function columnsAreCompatible(AnonymousSiebelColumn $child, AnonymousSiebelColumn $parent): bool
    {
        $childType = strtolower(trim((string) ($child->getRelationValue('dataType')?->data_type_name ?? $child->data_type ?? '')));
        $parentType = strtolower(trim((string) ($parent->getRelationValue('dataType')?->data_type_name ?? $parent->data_type ?? '')));

        if ($childType === '' || $parentType === '') {
            return true;
        }

        if ($childType === $parentType) {
            return true;
        }

        return $this->dataTypeCategory($childType) === $this->dataTypeCategory($parentType);
    }

    protected function dataTypeCategory(string $type): string
    {
        $type = strtolower($type);

        foreach (['varchar2', 'nvarchar2', 'char', 'nchar', 'clob'] as $charType) {
            if (str_contains($type, $charType)) {
                return 'char';
            }
        }

        foreach (['number', 'integer', 'float', 'binary_float', 'binary_double', 'decimal'] as $numType) {
            if (str_contains($type, $numType)) {
                return 'number';
            }
        }

        foreach (['date', 'timestamp'] as $dateType) {
            if (str_contains($type, $dateType)) {
                return 'date';
            }
        }

        return $type;
    }

    protected function oracleIdentifier(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
        $clean = preg_replace('/_+/', '_', (string) $clean);
        $clean = trim((string) $clean, '_');
        if ($clean === '') {
            $clean = 'OBJ';
        }

        if (strlen($clean) <= 30) {
            return $clean;
        }

        $hash = substr(md5($clean), 0, 8);
        $baseLen = 30 - 1 - 8;
        return substr($clean, 0, $baseLen) . '_' . $hash;
    }

    protected function rewritePackageSqlBlock(string $block, array $rewriteContext): string
    {
        $prefix = $rewriteContext['table_prefix'] ?? null;

        if (! is_string($prefix) || $prefix === '') {
            return $block;
        }

        preg_match_all('/\bcreate\s+table\s+([A-Za-z0-9_]+)\b/i', $block, $matches);
        $tableNames = array_values(array_unique($matches[1] ?? []));

        if ($tableNames === []) {
            return $block;
        }

        foreach ($tableNames as $tableName) {
            if (! is_string($tableName) || $tableName === '' || str_contains($tableName, '.')) {
                continue;
            }

            $prefixed = $this->oracleIdentifier($prefix . '_' . $tableName);
            $block = preg_replace('/\b' . preg_quote($tableName, '/') . '\b/', $prefixed, $block);
        }

        return $this->rewriteAnonymizationPackageOwner($block);
    }

    protected function anonymizationPackageOwner(): string
    {
        $owner = strtoupper(trim((string) config('anonymizer.package_owner', 'ANON_DATA')));
        $owner = preg_replace('/[^A-Z0-9_$#]/', '', $owner) ?: '';

        return $owner !== '' ? $owner : 'ANON_DATA';
    }

    protected function mapAnonymizationPackageOwner(string $owner): string
    {
        $owner = strtoupper(trim($owner));

        if ($owner === 'ANON_DATA') {
            return $this->anonymizationPackageOwner();
        }

        return $owner;
    }

    protected function rewriteAnonymizationPackageOwner(string $sql): string
    {
        $owner = $this->anonymizationPackageOwner();
        if ($owner === 'ANON_DATA' || trim($sql) === '') {
            return $sql;
        }

        return preg_replace('/\bANON_DATA\.(PKG_ANON_[A-Za-z0-9_$#]+)\b/i', $owner . '.$1', $sql) ?? $sql;
    }

    protected function buildSeedMapContext(Collection $columns, array $seedProviders, array $rewriteContext, ?AnonymizationJobs $job = null): array
    {
        $providers = [];

        $targetSchema = $rewriteContext['target_schema'] ?? null;
        $prefix = $rewriteContext['table_prefix'] ?? null;
        $tableMap = $rewriteContext['tables_by_id'] ?? [];

        $seedStoreMode = strtolower(trim((string) ($job?->seed_store_mode ?? ($rewriteContext['seed_store_mode'] ?? 'temporary'))));
        $seedStoreSchema = trim((string) ($job?->seed_store_schema ?? ($rewriteContext['seed_store_schema'] ?? '')));
        $seedStorePrefix = trim((string) ($job?->seed_store_prefix ?? ($rewriteContext['seed_store_prefix'] ?? '')));

        $inlineMasking = ($rewriteContext['masking_mode'] ?? '') === 'inline';
        $sourceAlias = $inlineMasking ? 'src' : 'tgt';

        $isPersistent = $seedStoreMode === 'persistent';
        if ($seedStoreSchema === '') {
            $seedStoreSchema = (string) $targetSchema;
        }

        if ($seedStorePrefix === '') {
            $seedStorePrefix = (string) $prefix;
        }

        if (! is_string($targetSchema) || $targetSchema === '' || ! is_string($prefix) || $prefix === '' || ! is_array($tableMap)) {
            return [];
        }

        if ($isPersistent && (! is_string($seedStoreSchema) || $seedStoreSchema === '' || ! is_string($seedStorePrefix) || $seedStorePrefix === '')) {
            return [];
        }

        $providerIds = [];

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            if (! $this->columnRequiresSeed($column, $method)) {
                continue;
            }

            $provider = $seedProviders[$column->id]['provider'] ?? null;
            if ($provider instanceof AnonymousSiebelColumn) {
                $providerIds[(int) $provider->id] = true;
            }
        }

        foreach (array_keys($providerIds) as $providerId) {
            // @var AnonymousSiebelColumn|null $provider
            $provider = $columns->firstWhere('id', $providerId);
            if (! $provider) {
                continue;
            }

            $providerTable = $provider->getRelationValue('table');
            $tableId = (int) ($providerTable?->getKey() ?? $provider->table_id ?? 0);
            $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;

            if (! is_array($mapped) || ! isset($mapped['target_qualified'], $mapped['source_table'])) {
                continue;
            }

            $seedMapName = $this->oracleIdentifier(
                ($isPersistent ? $seedStorePrefix : $prefix)
                    . '_SEEDMAP_' . ($mapped['source_table'] ?? 'T') . '_' . ($provider->column_name ?? 'C')
            );

            // Load dataType so DDL uses the canonical Oracle column type.
            if (! $provider->relationLoaded('dataType')) {
                $provider->loadMissing('dataType');
            }

            $columnType = $this->oracleColumnTypeForColumn($provider);

            // Compute the actual anonymized expression from the provider's method.
            // This ensures the seed map stores old_value → anonymized_value,
            // not an identity mapping (old_value → old_value).
            $providerMethod = $this->resolveMethodForColumn($provider);
            $anonymizedExpr = $this->anonymizedExpressionForSeedMap(
                $provider,
                $providerMethod,
                $rewriteContext,
                $inlineMasking ? $sourceAlias : 'tgt'
            );

            if ($anonymizedExpr !== null) {
                $seedExpr = $anonymizedExpr;
            } else {
                // Fallback to raw column reference when expression can't be extracted.
                $seedExpr = $this->seedExpressionForProvider($provider);
                $seedExpr = $this->renderSeedExpressionPlaceholders($seedExpr, $rewriteContext);
                if ($inlineMasking) {
                    $seedExpr = str_replace('tgt.', $sourceAlias . '.', $seedExpr);
                }
            }

            $providers[(int) $providerId] = [
                'provider_id' => (int) $providerId,
                'provider_column' => $provider->column_name,
                'provider_table' => $inlineMasking ? $mapped['source_qualified'] : $mapped['target_qualified'],
                'seed_expression' => $seedExpr,
                'seed_map_table' => ($isPersistent ? $seedStoreSchema : $targetSchema) . '.' . $seedMapName,
                'seed_map_persistence' => $isPersistent ? 'persistent' : 'temporary',
                'old_value_type' => $columnType,
                'new_value_type' => $columnType,
                'source_alias' => $sourceAlias,
            ];
        }

        return [
            'providers' => $providers,
        ];
    }

    protected function renderSeedExpressionPlaceholders(string $expression, array $rewriteContext): string
    {
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";
        $jobSeed = $rewriteContext['job_seed'] ?? '';

        return str_replace(
            ['{{JOB_SEED_LITERAL}}', '{{JOB_SEED}}'],
            [is_string($jobSeedLiteral) ? $jobSeedLiteral : "''", is_string($jobSeed) ? $jobSeed : ''],
            $expression
        );
    }

    protected function renderSeedMapTables(array $seedMapContext): array
    {
        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers) || $providers === []) {
            return [];
        }

        $lines = [];
        foreach ($providers as $provider) {
            $seedMapTable = $provider['seed_map_table'] ?? null;
            $providerTable = $provider['provider_table'] ?? null;
            $providerColumn = $provider['provider_column'] ?? null;
            $seedExpr = $provider['seed_expression'] ?? null;
            $persistence = $provider['seed_map_persistence'] ?? 'temporary';
            $oldValueType = $provider['old_value_type'] ?? 'VARCHAR2(4000)';
            $newValueType = $provider['new_value_type'] ?? 'VARCHAR2(4000)';
            $sourceAlias = $provider['source_alias'] ?? 'tgt';

            if (! $seedMapTable || ! $providerTable || ! $providerColumn || ! $seedExpr) {
                continue;
            }

            $lines[] = '-- Seed map for: ' . $providerTable . '.' . $providerColumn;

            if ($persistence === 'persistent') {
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'CREATE TABLE {$seedMapTable} (old_value {$oldValueType} PRIMARY KEY, new_value {$newValueType})';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE != -955 THEN RAISE; END IF;';
                $lines[] = 'END;';
                $lines[] = '/';

                $lines[] = 'MERGE INTO ' . $seedMapTable . ' sm';
                $lines[] = 'USING (';
                $lines[] = '  SELECT DISTINCT';
                $lines[] = '    ' . $sourceAlias . '.' . $providerColumn . ' AS old_value,';
                $lines[] = '    ' . $seedExpr . ' AS new_value';
                $lines[] = '  FROM ' . $providerTable . ' ' . $sourceAlias;
                $lines[] = '  WHERE ' . $sourceAlias . '.' . $providerColumn . ' IS NOT NULL';
                $lines[] = ') src';
                $lines[] = 'ON (sm.old_value = src.old_value)';
                $lines[] = 'WHEN MATCHED THEN';
                $lines[] = '  UPDATE SET sm.new_value = src.new_value';
                $lines[] = 'WHEN NOT MATCHED THEN';
                $lines[] = '  INSERT (old_value, new_value) VALUES (src.old_value, src.new_value);';
                $lines[] = '';
            } else {
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$seedMapTable} PURGE';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE NOT IN (-942, -12083) THEN RAISE; END IF;';
                $lines[] = 'END;';
                $lines[] = '/';
                $lines[] = 'CREATE TABLE ' . $seedMapTable . ' AS';
                $lines[] = 'SELECT';
                $lines[] = '  ' . $sourceAlias . '.' . $providerColumn . ' AS old_value,';
                $lines[] = '  ' . $seedExpr . ' AS new_value';
                $lines[] = 'FROM ' . $providerTable . ' ' . $sourceAlias;
                $lines[] = 'WHERE ' . $sourceAlias . '.' . $providerColumn . ' IS NOT NULL;';
                $lines[] = '';
            }
        }

        return $lines;
    }

    protected function seedMapForColumn(?AnonymousSiebelColumn $provider, array $seedMapContext): array
    {
        if (! $provider) {
            return [];
        }

        $providers = $seedMapContext['providers'] ?? [];
        if (! is_array($providers)) {
            return [];
        }

        return $providers[(int) $provider->id] ?? [];
    }

    protected function seedQualifiedReference(
        AnonymousSiebelColumn $column,
        ?AnonymousSiebelColumn $provider,
        string $fallbackQualifiedTable,
        array $rewriteContext
    ): string {
        $subject = $provider ?: $column;
        $subjectTable = $subject->getRelationValue('table');
        $tableId = (int) ($subjectTable?->getKey() ?? $subject->table_id ?? 0);
        $mapped = ($rewriteContext['tables_by_id'] ?? [])[$tableId] ?? null;

        $inlineMasking = ($rewriteContext['masking_mode'] ?? '') === 'inline';
        $qualified = $inlineMasking
            ? ($mapped['source_qualified'] ?? null)
            : ($mapped['target_qualified'] ?? null);
        if ($qualified) {
            return $qualified . '.' . ($subject->column_name ?? '');
        }

        if ($provider) {
            return $this->describeColumn($provider);
        }

        return $fallbackQualifiedTable
            ? ($fallbackQualifiedTable . '.' . ($column->column_name ?? ''))
            : ($column->column_name ?? '');
    }

    protected function jobHeaderMetadata(?AnonymizationJobs $job): array
    {
        if (! $job) {
            return [
                'title' => 'Ad-hoc Anonymization Preview',
                'job_type' => null,
                'output' => null,
            ];
        }

        return [
            'title' => 'Anonymization Job: ' . $job->name,
            'job_type' => $job->job_type ? Str::title($job->job_type) : null,
            'output' => $job->output_format ? Str::upper($job->output_format) : null,
        ];
    }

    protected function buildHeaderLines(array $jobMeta, array $rewriteContext = []): array
    {
        $targetSchema = Str::upper(trim((string) ($rewriteContext['target_schema'] ?? '')));
        $tableScopeMode = trim((string) ($rewriteContext['table_scope_mode'] ?? ''));

        // Collect distinct source schemas for header instructions.
        $sourceSchemas = [];
        $tablesById = $rewriteContext['tables_by_id'] ?? [];
        if (is_array($tablesById)) {
            $sourceSchemas = collect($tablesById)
                ->map(fn($m) => is_array($m) ? Str::upper(explode('.', (string) ($m['source_qualified'] ?? ''), 2)[0] ?? '') : '')
                ->filter(fn($s) => $s !== '')
                ->unique()
                ->values()
                ->all();
        }

        $lines = [
            $this->commentDivider('='),
            '-- ' . $jobMeta['title'],
            '-- Generated: ' . now()->toDateString() . ' ' . now()->toTimeString(),
        ];

        if ($jobMeta['job_type']) {
            $lines[] = '-- Job Type: ' . $jobMeta['job_type'];
        }

        if ($jobMeta['output']) {
            $lines[] = '-- Output Format: ' . $jobMeta['output'];
        }

        if ($tableScopeMode !== '') {
            $scopeLabel = match ($tableScopeMode) {
                'explicit-columns' => 'explicit selected columns (plus dependencies)',
                'full-schema' => 'full schema expansion',
                default => 'selection-derived scope',
            };
            $lines[] = '-- Table Scope: ' . $scopeLabel;
        }

        // Connection guidance
        if ($targetSchema !== '' && $sourceSchemas !== []) {
            $lines[] = '--';
            $lines[] = '-- HOW TO RUN: Connect and execute. The script auto-detects privileges.';
            $lines[] = '--   Best: a DBA or privileged user runs once and everything works.';
            $lines[] = '--   Otherwise: the script stops with guidance if split-user setup is needed.';
        }

        $lines[] = '-- SQL*Plus / SQLcl runtime settings';
        $lines[] = 'SET SERVEROUTPUT ON SIZE UNLIMITED';
        $lines[] = 'WHENEVER SQLERROR EXIT SQL.SQLCODE';

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $lines = array_merge($lines, $this->buildPrivilegePreflightSection());

        return $lines;
    }

    protected function buildDeterministicRandomSeedSection(array $rewriteContext): array
    {
        $jobSeed = trim((string) ($rewriteContext['job_seed'] ?? ''));
        if ($jobSeed === '') {
            return [];
        }

        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? $this->oracleStringLiteral($jobSeed);
        if (! is_string($jobSeedLiteral) || $jobSeedLiteral === '') {
            $jobSeedLiteral = $this->oracleStringLiteral($jobSeed);
        }

        return [
            $this->commentDivider('='),
            '-- Deterministic Randomness',
            '-- Seeds DBMS_RANDOM from job seed so random-based methods are repeatable.',
            $this->commentDivider('='),
            'BEGIN',
            '  DBMS_RANDOM.SEED(' . $jobSeedLiteral . ');',
            'END;',
            '/',
            '',
        ];
    }

    protected function buildPrivilegePreflightSection(): array
    {
        return [
            $this->commentDivider('='),
            '-- Note: CREATE VIEW / CTAS require direct SELECT grants (role-only access is not sufficient).',
            '-- The preflight below auto-detects and configures privileges where possible.',
            $this->commentDivider('='),
            '',
        ];
    }

    protected function commentDivider(string $char = '=', int $length = 70): string
    {
        return '-- ' . str_repeat($char, $length);
    }

    protected function methodHeading(?AnonymizationMethods $method): string
    {
        if (! $method) {
            return '-- Method: (not assigned)';
        }

        $label = '-- Method: ' . $method->name;

        $categorySummary = $method->categorySummary();
        if ($categorySummary) {
            $label .= ' [' . $categorySummary . ']';
        }

        return $label;
    }

    protected function columnsListing(Collection $columns, array $orderedIds): string
    {
        // Ensure all IDs are integers for array_flip
        $orderedIds = array_values(array_filter(array_map('intval', $orderedIds), fn($id) => $id > 0));
        $idPosition = array_flip($orderedIds);

        // @var Collection<int, AnonymousSiebelColumn> $columns
        $columns = $columns->sortBy(fn(AnonymousSiebelColumn $column) => $idPosition[(int) $column->id] ?? PHP_INT_MAX);

        $lines = ['-- Columns:'];

        foreach ($columns as $column) {
            $dependencies = $this->dependencyNames($column, $orderedIds);
            $line = sprintf('--   - %s', $this->describeColumn($column));

            if ($dependencies !== []) {
                $line .= ' (depends on: ' . implode(', ', $dependencies) . ')';
            }

            $lines[] = $line;
        }

        return implode(PHP_EOL, $lines);
    }

    protected function dependencyNames(AnonymousSiebelColumn $column, array $selectedIds): array
    {
        // Ensure all IDs are integers for array_flip
        $selectedIds = array_values(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0));
        $selectedLookup = array_flip($selectedIds);

        $parents = $column->getRelationValue('parentColumns') ?? collect();

        return $parents
            ->filter(fn(AnonymousSiebelColumn $parent) => isset($selectedLookup[(int) $parent->id]))
            ->map(fn(AnonymousSiebelColumn $parent) => $this->describeColumn($parent))
            ->unique()
            ->values()
            ->all();
    }

    protected function describeColumn(AnonymousSiebelColumn $column): string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $segments = array_filter([
            $database?->database_name,
            $schema?->schema_name,
            $table?->table_name,
            $column->column_name,
        ]);

        return implode('.', $segments);
    }

    protected function resolveMethodForColumn(AnonymousSiebelColumn $column): ?AnonymizationMethods
    {
        $columnId = $column->id;

        // Check cache first to avoid redundant lookups (critical for large column sets)
        if (array_key_exists($columnId, $this->methodCache)) {
            return $this->methodCache[$columnId];
        }

        // Rule-based resolution: use the column's assigned rule to resolve the method.
        // The job's strategy (stored on $this->currentJobStrategy) guides which method
        // the rule returns; if no strategy is set, the rule's default method is used.
        $rules = $column->getRelationValue('anonymizationRule');
        $rule = $rules instanceof \Illuminate\Database\Eloquent\Collection ? $rules->first() : $rules;

        if (! $rule && ! $column->relationLoaded('anonymizationRule')) {
            $rule = $column->anonymizationRule()->with('methods')->first();
        }

        if ($rule) {
            // Ensure methods are loaded on the rule
            if (! $rule->relationLoaded('methods')) {
                $rule->load('methods');
            }

            $resolved = $rule->resolveMethod($this->currentJobStrategy ?? null);

            if ($resolved) {
                $this->methodCache[$columnId] = $resolved;
                return $resolved;
            }
        }

        $methodId = $column->pivot->anonymization_method_id ?? null;

        if ($methodId) {
            // Pivot method IDs are treated as a fallback only when no rule-based
            // method could be resolved. This keeps existing jobs aligned with
            // rule default/strategy updates.
            $resolved = $column->anonymizationMethods->firstWhere('id', $methodId);

            if ($resolved) {
                $this->methodCache[$columnId] = $resolved;
                return $resolved;
            }

            $resolved = AnonymizationMethods::withTrashed()->find($methodId);
            $this->methodCache[$columnId] = $resolved;
            return $resolved;
        }

        // Legacy fallback: direct column→method association
        $resolved = $column->anonymizationMethods->first();
        $this->methodCache[$columnId] = $resolved;
        return $resolved;
    }

    protected function topologicallySortColumns(Collection $columns): Collection
    {
        // Use iterative Kahn's algorithm instead of recursive DFS to avoid
        // stack overflow on large column sets (schemas with thousands of columns).
        // @var Collection<int, AnonymousSiebelColumn> $columns
        $columns = $columns->keyBy(fn(AnonymousSiebelColumn $column) => $column->id);

        if ($columns->count() <= 1) {
            return $columns->values();
        }

        // Build adjacency list and in-degree count
        $inDegree = [];
        $adjacency = [];

        foreach ($columns as $column) {
            $inDegree[$column->id] = 0;
            $adjacency[$column->id] = [];
        }

        foreach ($columns as $column) {
            $parents = $column->getRelationValue('parentColumns') ?? collect();

            foreach ($parents as $parent) {
                if ($columns->has($parent->id)) {
                    // Parent -> Child edge: parent must come before child
                    $adjacency[$parent->id][] = $column->id;
                    $inDegree[$column->id]++;
                }
            }
        }

        // Also add ordering edges from FK relationship metadata for columns that have no
        // explicit anonymous_siebel_column_dependencies rows. Without this, FK child tables
        // may be sorted ahead of their PK parent tables, causing deferred UPDATE statements
        // to run against stale data.
        //
        // Build TABLE_NAME → ROW_ID column id once for O(n) lookup.
        $rowIdByTableName = [];
        foreach ($columns as $candidate) {
            if (strtoupper((string) ($candidate->column_name ?? '')) === 'ROW_ID') {
                $tbl = $candidate->getRelationValue('table');
                $tk  = $tbl ? strtoupper((string) ($tbl->table_name ?? '')) : '';
                if ($tk !== '') {
                    $rowIdByTableName[$tk] = (int) $candidate->id;
                }
            }
        }

        foreach ($columns as $column) {
            // Skip columns that already have explicit parent edges applied above.
            $parents = $column->getRelationValue('parentColumns') ?? collect();
            if ($parents->isNotEmpty()) {
                continue;
            }

            $relationships = $this->resolveForeignKeyRelationships($column);
            foreach ($relationships as $rel) {
                if (strtoupper((string) ($rel['direction'] ?? 'OUTBOUND')) !== 'OUTBOUND') {
                    continue;
                }
                $pCol   = strtoupper(trim((string) ($rel['column'] ?? 'ROW_ID')));
                $pTable = strtoupper(trim((string) ($rel['table'] ?? '')));
                if ($pCol !== 'ROW_ID' || $pTable === '') {
                    continue;
                }
                $parentColId = $rowIdByTableName[$pTable] ?? null;
                if ($parentColId && $columns->has($parentColId) && $parentColId !== (int) $column->id) {
                    $adjacency[$parentColId][] = $column->id;
                    $inDegree[$column->id]++;
                }
            }
        }

        // Collect all nodes with no incoming edges (roots/seed providers)
        $queue = [];
        foreach ($inDegree as $nodeId => $degree) {
            if ($degree === 0) {
                $queue[] = $nodeId;
            }
        }

        // Sort initial queue for deterministic output
        sort($queue);

        $order = [];
        $processed = 0;

        // Process queue iteratively (Kahn's algorithm)
        while ($queue !== []) {
            $nodeId = array_shift($queue);
            $order[] = $nodeId;
            $processed++;

            // Collect and sort neighbors for deterministic ordering
            $neighbors = $adjacency[$nodeId] ?? [];
            sort($neighbors);

            foreach ($neighbors as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // Detect cycle: if we didn't process all nodes, there's a cycle
        if ($processed !== count($columns)) {
            // Fall back to alphabetical order when a cycle exists
            return $columns
                ->values()
                ->sortBy(fn(AnonymousSiebelColumn $column) => $this->describeColumn($column))
                ->values();
        }

        return collect($order)
            ->map(fn(int $id) => $columns->get($id))
            ->filter()
            ->values();
    }
}
