<?php

namespace App\Services\Anonymizer;

use App\Services\Anonymizer\Concerns\BuildsDoubleSeededDeterministicOracleScripts;
use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymizationMethods;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnonymizationJobScriptService
{
    use BuildsDoubleSeededDeterministicOracleScripts;

    private const WHERE_IN_CHUNK_SIZE = 10000;

    protected const SEED_PLACEHOLDERS = [
        '{{SEED_MAP_LOOKUP}}',
        '{{SEED_EXPR}}',
        '{{SEED_SOURCE_QUALIFIED}}',
    ];

    // Cache for resolved methods to avoid redundant lookups.
    // Key: column ID, Value: AnonymizationMethods|null
    // @var array<int, AnonymizationMethods|null>
    protected array $methodCache = [];

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
        $typeName = strtolower(trim((string) ($column->data_type ?? '')));

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

    public function buildForJob(AnonymizationJobs $job): string
    {
        $job->loadMissing([
            'columns.anonymizationMethods.packages',
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

        $lines[] = $this->commentDivider('=');
        $targetMode = $this->normalizeJobOption((string) ($rewriteContext['target_table_mode'] ?? '')) ?: 'prefixed';
        $modeLabel = $targetMode === 'anon'
            ? 'mode INITIAL_* → ANON_*'
            : ('prefix ' . ($rewriteContext['table_prefix'] ?? 'none'));
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
            $lines[] = '-- Runs after target table clones are created, before seed maps and masking updates.';
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
            $lines[] = '-- Runs after masking updates and seed maps are applied.';
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

        foreach (array_chunk($columnIds, $chunkSize) as $chunk) {
            $batch = AnonymousSiebelColumn::query()
                ->with([
                    'anonymizationMethods', // Load methods without deep packages initially
                    'table.schema.database',
                    'dataType',
                ])
                ->whereIn('id', $chunk)
                ->get();

            // Use push with spread to preserve items without losing Eloquent collection type
            foreach ($batch as $item) {
                $columns->push($item);
            }

            // Free up memory between chunks
            unset($batch);
        }

        // Load parentColumns relationships separately with just the IDs needed
        // to avoid N+1 but without the deep eager loading overhead
        // Convert to Eloquent Collection to enable lazy loading
        $columns = AnonymousSiebelColumn::hydrate($columns->all());
        $columns->load(['parentColumns']);

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

        foreach (array_chunk($columnIds, $batchSize) as $chunkIndex => $chunk) {
            $batch = AnonymousSiebelColumn::query()
                ->with([
                    'table.schema.database',
                    'dataType',
                    'parentColumns',
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

    public function prepareChunkedContextForColumnIds(array $columnIds, ?AnonymizationJobs $job = null): array
    {
        $columnIds = array_values(array_unique(array_filter(array_map('intval', $columnIds))));

        Log::info('AnonymizationJobScriptService: preparing chunked context', [
            'job_id' => $job?->id,
            'column_count' => count($columnIds),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        if ($columnIds === []) {
            return [
                'ordered_ids' => [],
                'prefix_sql' => '',
                'suffix_sql' => '',
                'seed_provider_map' => [],
                'rewrite_context' => [],
                'seed_map_context' => [],
                'halted' => false,
            ];
        }

        $columns = collect();
        $batchSize = 500;
        $processed = 0;

        foreach (array_chunk($columnIds, $batchSize) as $chunk) {
            $batch = AnonymousSiebelColumn::query()
                ->with([
                    'anonymizationMethods',
                    'table.schema.database',
                    'dataType',
                    'parentColumns',
                ])
                ->whereIn('id', $chunk)
                ->get();

            foreach ($batch as $item) {
                $columns->push($item);
            }

            unset($batch);

            $processed += count($chunk);
            Log::info('AnonymizationJobScriptService: chunked context load progress', [
                'job_id' => $job?->id,
                'processed' => $processed,
                'total' => count($columnIds),
                'percent' => count($columnIds) > 0 ? round(($processed / count($columnIds)) * 100, 1) : 100,
                'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
        }

        if (! $columns instanceof \Illuminate\Database\Eloquent\Collection) {
            $columns = new \Illuminate\Database\Eloquent\Collection($columns->all());
        }

        $methodItems = $columns->pluck('anonymizationMethods')->flatten()->filter()->unique('id');
        if ($methodItems->isNotEmpty()) {
            $methods = AnonymizationMethods::hydrate($methodItems->values()->all());
            $methods->loadMissing('packages');
        }

        Log::info('AnonymizationJobScriptService: chunked context methods loaded', [
            'job_id' => $job?->id,
            'method_count' => $methodItems->count(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $ordered = $this->topologicallySortColumns($columns);
        if ($ordered->isEmpty()) {
            return [
                'ordered_ids' => [],
                'prefix_sql' => '',
                'suffix_sql' => '',
                'seed_provider_map' => [],
                'rewrite_context' => [],
                'seed_map_context' => [],
                'halted' => false,
            ];
        }

        Log::info('AnonymizationJobScriptService: chunked context ordered columns', [
            'job_id' => $job?->id,
            'ordered_count' => $ordered->count(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $seedProviders = $this->resolveSeedProviders($ordered);
        Log::info('AnonymizationJobScriptService: chunked context seed providers resolved', [
            'job_id' => $job?->id,
            'provider_count' => count($seedProviders),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
        $rewriteContext = $this->buildJobTableRewriteContext($ordered, $job, true);
        $seedMapContext = $this->jobUsesSeedMapPlaceholders($ordered)
            ? $this->buildSeedMapContext($ordered, $seedProviders, $rewriteContext, $job)
            : [];

        Log::info('AnonymizationJobScriptService: chunked context rewrite/seed map ready', [
            'job_id' => $job?->id,
            'seed_map_count' => is_array($seedMapContext['providers'] ?? null) ? count($seedMapContext['providers']) : 0,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $prefixLines = $this->buildHeaderLines($this->jobHeaderMetadata($job), $rewriteContext);

        $contractReview = $this->validateSeedContracts($ordered, $seedProviders, $seedMapContext);

        if ($contractReview['errors']->isNotEmpty() || $contractReview['warnings']->isNotEmpty()) {
            $prefixLines = array_merge($prefixLines, $this->renderContractReview($contractReview));

            if ($contractReview['errors']->isNotEmpty()) {
                $prefixLines[] = '-- SQL generation halted due to blocking seed contract violations.';

                return [
                    'ordered_ids' => [],
                    'prefix_sql' => trim(implode(PHP_EOL, $prefixLines)),
                    'suffix_sql' => '',
                    'seed_provider_map' => [],
                    'rewrite_context' => $rewriteContext,
                    'seed_map_context' => $seedMapContext,
                    'halted' => true,
                ];
            }
        }

        $packages = $this->collectPackagesFromColumns($ordered);
        if ($packages->isNotEmpty()) {
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '-- Package Dependencies';
            $prefixLines[] = '-- Ordered for deterministic exports';
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';

            foreach ($packages as $package) {
                $prefixLines[] = $this->commentDivider('-');
                $prefixLines[] = '-- Package: ' . $package->display_label;

                if ($package->summary) {
                    $prefixLines[] = '-- ' . trim($package->summary);
                }

                foreach ($package->compiledSqlBlocks() as $block) {
                    $prefixLines[] = trim($this->rewritePackageSqlBlock((string) $block, $rewriteContext));
                    $prefixLines[] = '';
                }
            }

            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';
        }

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);
        if ($tableCloneStatements !== []) {
            $prefixLines = array_merge($prefixLines, $tableCloneStatements);
        }

        $preMaskSql = trim((string) ($job?->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '-- Pre-mask SQL';
            $prefixLines[] = '-- Runs after target table clones are created, before seed maps and masking updates.';
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';
            $prefixLines = array_merge($prefixLines, preg_split('/\R/', $preMaskSql) ?: []);
            $prefixLines[] = '';
            $prefixLines[] = $this->commentDivider('=');
            $prefixLines[] = '';
        }

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

        $prefixLines[] = $this->commentDivider('=');
        $prefixLines[] = '-- Column Masking (dependency-ordered)';
        $prefixLines[] = '-- Columns are processed in topological order: parents before children.';
        $prefixLines[] = $this->commentDivider('=');
        $prefixLines[] = '';

        $suffixLines = [];
        $skipConstraints = count($columnIds) > 2000;
        $postMaskSql = trim((string) ($job?->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '-- Post-mask SQL';
            $suffixLines[] = '-- Runs after masking updates complete.';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
            $suffixLines = array_merge($suffixLines, preg_split('/\R/', $postMaskSql) ?: []);
            $suffixLines[] = '';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
        }

        if ($skipConstraints) {
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '-- Constraints Skipped';
            $suffixLines[] = '-- PK/FK generation is skipped for large batch jobs to avoid memory spikes.';
            $suffixLines[] = '-- Re-run with a smaller scope if constraints are required.';
            $suffixLines[] = $this->commentDivider('=');
            $suffixLines[] = '';
        } else {
            $pkStatements = $this->buildPrimaryKeyStatements($rewriteContext);
            if ($pkStatements !== []) {
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '-- Primary Keys';
                $suffixLines[] = '-- Add ROW_ID primary keys so foreign keys can be recreated.';
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '';
                $suffixLines = array_merge($suffixLines, $pkStatements);
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '';
            }

            $fkStatements = $this->buildForeignKeyStatements($rewriteContext);
            if ($fkStatements !== []) {
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '-- Foreign Keys';
                $suffixLines[] = '-- Recreate parent/child relationships within the target schema.';
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '';
                $suffixLines = array_merge($suffixLines, $fkStatements);
                $suffixLines[] = $this->commentDivider('=');
                $suffixLines[] = '';
            }
        }

        $hygiene = $this->renderSeedMapHygieneSection($seedMapContext, $job);
        if ($hygiene !== []) {
            $suffixLines = array_merge($suffixLines, $hygiene);
        }

        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '-- Finalize';
        $suffixLines[] = 'COMMIT;';
        $suffixLines[] = $this->commentDivider('=');
        $suffixLines[] = '';

        $seedProviderMap = [];
        foreach ($seedProviders as $columnId => $provider) {
            $seedProviderMap[(int) $columnId] = [
                'provider_id' => isset($provider['provider']) ? (int) ($provider['provider']?->id ?? 0) : 0,
                'expression' => $provider['expression'] ?? null,
            ];
        }

        Log::info('AnonymizationJobScriptService: chunked context prepared', [
            'job_id' => $job?->id,
            'ordered_count' => $ordered->count(),
            'prefix_length' => strlen(trim(implode(PHP_EOL, $prefixLines))),
            'suffix_length' => strlen(trim(implode(PHP_EOL, $suffixLines))),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        return [
            'ordered_ids' => $ordered->pluck('id')->map(fn($id) => (int) $id)->all(),
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
        $orderedIds = array_values(array_unique(array_filter(array_map('intval', $orderedIds))));
        if ($orderedIds === []) {
            return '';
        }

        $columns = AnonymousSiebelColumn::query()
            ->with([
                'anonymizationMethods',
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

            $tablesById[$tableId] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
                'select_list' => $selectList,
                'long_columns' => $longColumns,
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

        $tableCloneStatements = $this->renderJobTableClones($rewriteContext);
        if ($tableCloneStatements !== []) {
            $lines = array_merge($lines, $tableCloneStatements);
        }

        $preMaskSql = trim((string) ($job?->pre_mask_sql ?? ''));
        if ($preMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Pre-mask SQL';
            $lines[] = '-- Runs after target table clones are created, before seed maps and masking updates.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, preg_split('/\R/', $preMaskSql) ?: []);
            $lines[] = '';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        $seedMapStatements = $this->renderSeedMapTables($seedMapContext);
        if ($seedMapStatements !== []) {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Seed Maps (relationship preservation)';
            $lines[] = '-- Lookup tables keep dependent keys aligned with seed providers.';
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
            $lines = array_merge($lines, $seedMapStatements);
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        // Process columns in topological order to maintain dependency cascades.
        // Parent/seed-providing columns must be masked before their dependents.
        // This ensures FK relationships remain intact when both parent and child columns are anonymized.

        $lines[] = $this->commentDivider('=');
        $lines[] = '-- Column Masking (dependency-ordered)';
        $lines[] = '-- Columns are processed in topological order: parents before children.';
        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $orderedIds = $ordered->pluck('id')->map(fn($id) => (int) $id)->all();
        $lastMethodId = null;

        // @var AnonymousSiebelColumn $column
        foreach ($ordered as $column) {
            $method = $this->resolveMethodForColumn($column);
            $methodId = $method?->id ?? 'none';

            // Emit a method heading when transitioning between methods (for readability).
            if ($methodId !== $lastMethodId) {
                $lines[] = $this->commentDivider('-');
                $lines[] = $this->methodHeading($method);
                $lastMethodId = $methodId;
            }

            $sqlBlock = trim((string) ($method?->sql_block ?? ''));

            // Annotate each column with its dependencies.
            $dependencies = $this->dependencyNames($column, $orderedIds);
            $depNote = $dependencies !== []
                ? ' (depends on: ' . implode(', ', $dependencies) . ')'
                : '';

            $lines[] = '-- Column: ' . $this->describeColumn($column) . $depNote;

            if ($sqlBlock === '') {
                $lines[] = '-- No SQL block defined for this method.';
            } else {
                // Render SQL for this single column, preserving dependency order.
                foreach ($this->renderSqlBlocksForColumns($sqlBlock, collect([$column]), $seedProviders, $rewriteContext, $seedMapContext) as $renderedBlock) {
                    $lines[] = $renderedBlock;
                }
            }

            $lines[] = '';
        }

        $postMaskSql = trim((string) ($job?->post_mask_sql ?? ''));
        if ($postMaskSql !== '') {
            $lines[] = $this->commentDivider('=');
            $lines[] = '-- Post-mask SQL';
            $lines[] = '-- Runs after masking updates complete.';
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

        // Commit final DML so masking updates persist.
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

                $detail = $columnLabel . ': Seed consumer columns must declare at least one parent dependency.';
                $warnings->push($detail);
                $pushIssue('warning', $detail, 'missing_parent');
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

        // @var AnonymousSiebelColumn $column
        foreach ($columns as $column) {
            $method = $this->resolveMethodForColumn($column);
            $provider = $this->seedProviderForColumn($column, $method, $columns, $emittersByTable, $seedEmitters);

            $providers[$column->id] = [
                'provider' => $provider,
                'expression' => $this->seedExpressionForProvider($provider ?? $column),
            ];
        }

        return $providers;
    }

    protected function seedProviderForColumn(
        AnonymousSiebelColumn $column,
        ?AnonymizationMethods $method,
        Collection $selectedColumns,
        Collection $emittersByTable,
        Collection $seedEmitters
    ): ?AnonymousSiebelColumn {
        if (! $this->columnRequiresSeed($column, $method)) {
            return $this->columnProvidesSeed($column, $method) ? $column : null;
        }

        // Prefer an explicitly selected parent column as the seed provider.
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

        // Fall back to another seed emitter in the same table when no explicit parent is selected.
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

        // If still unresolved, fall back to the single remaining seed emitter in the job.
        $global = $seedEmitters
            ->filter(fn(AnonymousSiebelColumn $c) => $c->id !== $column->id)
            ->values();

        if ($global->count() === 1) {
            return $global->first();
        }

        // If no safe provider can be chosen, return null and let validation enforce explicit parents.
        return null;
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
        array $seedMapContext = []
    ): string {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        $tableId = (int) ($table?->getKey() ?? $column->table_id ?? 0);
        $tableMap = $rewriteContext['tables_by_id'] ?? [];
        $mapped = $tableId > 0 ? ($tableMap[$tableId] ?? null) : null;

        $renderSchemaName = $mapped['target_schema'] ?? ($schema?->schema_name ?? '');
        $renderTableName = $mapped['target_table'] ?? ($table?->table_name ?? '');

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
        $seedExpression = $seedProvider['expression'] ?? ('tgt.' . $seedColumnName);

        $seedMap = $this->seedMapForColumn($seedProvider['provider'] ?? null, $seedMapContext);
        $seedMapTable = $seedMap['seed_map_table'] ?? '';
        $seedMapLookup = $seedMapTable !== ''
            ? '(SELECT sm.new_value FROM ' . $seedMapTable . ' sm WHERE sm.old_value = tgt.' . ($column->column_name ?? '') . ' AND ROWNUM = 1)'
            : '';

        $jobSeed = $rewriteContext['job_seed'] ?? '';
        $jobSeedLiteral = $rewriteContext['job_seed_literal'] ?? "''";

        $columnMaxLength = $this->oracleColumnMaxLength($column);
        $columnName = $column->column_name ?? '';
        $columnMaxLengthExpr = ($columnMaxLength > 0 && $columnMaxLength < 4000)
            ? (string) $columnMaxLength
            : ($columnName !== '' ? ('length(tgt.' . $columnName . ')') : '4000');

        $replacements = [
            '{{TABLE}}' => $qualifiedTable ?: ($renderTableName ?: ($table?->table_name ?? '{{TABLE}}')),
            '{{TABLE_NAME}}' => $renderTableName,
            '{{SCHEMA}}' => $renderSchemaName,
            '{{DATABASE}}' => $mapped ? '' : ($database?->database_name ?? ''),
            '{{COLUMN}}' => $column->column_name ?? '',
            '{{COLUMN_MAX_LEN}}' => (string) $columnMaxLength,
            '{{COLUMN_MAX_LEN_EXPR}}' => $columnMaxLengthExpr,
            '{{ALIAS}}' => 'tgt',
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

        $rawReplace = $rewriteContext['raw_replace'] ?? [];
        if ($rawReplace !== []) {
            $rendered = str_replace(array_keys($rawReplace), array_values($rawReplace), $rendered);
        }

        return $rendered;
    }

    protected function buildJobTableRewriteContext(Collection $columns, ?AnonymizationJobs $job, bool $skipLongDetection = false): array
    {
        $targetSchema = $this->targetSchemaForJob($job);
        $tablePrefix = $this->tablePrefixForJob($job);
        $targetTableMode = $this->normalizeJobOption($job?->target_table_mode) ?: 'prefixed';

        if (! $targetSchema || ! $tablePrefix) {
            return [];
        }

        $tables = collect();

        // For FULL jobs, clone every table in the selected schema scope.
        if ($job && $job->job_type === AnonymizationJobs::TYPE_FULL) {
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
            }

            $parents = $column->getRelationValue('parentColumns') ?? collect();
            foreach ($parents as $parent) {
                $parentTable = $parent->getRelationValue('table');
                if ($parentTable) {
                    $tables->push($parentTable);
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

            $tablesById[$tableId] = [
                'source_schema' => $sourceSchema,
                'source_table' => $sourceTable,
                'source_qualified' => $sourceQualified,
                'target_schema' => $targetSchema,
                'target_table' => $targetTable,
                'target_qualified' => $targetQualified,
                'select_list' => $selectList,
                'long_columns' => $longColumns,
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

    protected function targetTableNameForSourceTable(string $sourceTable, string $tablePrefix, string $mode): string
    {
        $mode = $this->normalizeJobOption($mode);

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
            $lines[] = $prefix . '    IF SQLCODE != -942 THEN RAISE; END IF;';
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

        $lines = [];
        foreach ($tablesById as $mapping) {
            $source = $mapping['source_qualified'] ?? null;
            $target = $mapping['target_qualified'] ?? null;
            if (! $source || ! $target) {
                continue;
            }

            $selectList = $mapping['select_list'] ?? '*';
            $longColumns = $mapping['long_columns'] ?? [];

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
                $lines[] = 'FROM   ' . $source . ';';
            } else {
                $lines[] = 'CREATE TABLE ' . $target . ' AS';
                $lines[] = 'SELECT ' . $selectList;
                $lines[] = 'FROM   ' . $source . ';';
            }
            $lines[] = $this->commentDivider('=');
            $lines[] = '';
        }

        return $lines;
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

        return AnonymousSiebelColumn::query()
            ->with(['dataType'])
            ->whereIn('table_id', $tableIds)
            ->get()
            ->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0))
            ->all();
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

        $columns = AnonymousSiebelColumn::query()
            ->with(['dataType', 'table.schema'])
            ->whereIn('table_id', $tableIds)
            ->get();

        if ($columns->isEmpty()) {
            return [];
        }

        $tablesByIdentity = [];
        foreach ($tablesById as $tableId => $mapping) {
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

            $childMap = $tablesById[$childTableId] ?? null;
            if (! is_array($childMap)) {
                continue;
            }

            $childColumn = trim((string) ($column->column_name ?? ''));
            if ($childColumn === '') {
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

        $columnsByTable = AnonymousSiebelColumn::query()
            ->with(['dataType'])
            ->whereIn('table_id', $tableIds)
            ->get()
            ->groupBy(fn(AnonymousSiebelColumn $column) => (int) ($column->table_id ?? 0));

        $lines = [];
        $seen = [];

        foreach ($tablesById as $tableId => $mapping) {
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

        return $block;
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

            $seedExpr = $this->seedExpressionForProvider($provider);
            $seedExpr = $this->renderSeedExpressionPlaceholders($seedExpr, $rewriteContext);

            $providers[(int) $providerId] = [
                'provider_id' => (int) $providerId,
                'provider_column' => $provider->column_name,
                'provider_table' => $mapped['target_qualified'],
                'seed_expression' => $seedExpr,
                'seed_map_table' => ($isPersistent ? $seedStoreSchema : $targetSchema) . '.' . $seedMapName,
                'seed_map_persistence' => $isPersistent ? 'persistent' : 'temporary',
                'old_value_type' => $columnType,
                'new_value_type' => $columnType,
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
                $lines[] = '    tgt.' . $providerColumn . ' AS old_value,';
                $lines[] = '    ' . $seedExpr . ' AS new_value';
                $lines[] = '  FROM ' . $providerTable . ' tgt';
                $lines[] = ') src';
                $lines[] = 'ON (sm.old_value = src.old_value)';
                $lines[] = 'WHEN NOT MATCHED THEN';
                $lines[] = '  INSERT (old_value, new_value) VALUES (src.old_value, src.new_value);';
                $lines[] = '';
            } else {
                $lines[] = 'BEGIN';
                $lines[] = "  EXECUTE IMMEDIATE 'DROP TABLE {$seedMapTable} PURGE';";
                $lines[] = 'EXCEPTION';
                $lines[] = '  WHEN OTHERS THEN';
                $lines[] = '    IF SQLCODE != -942 THEN RAISE; END IF;';
                $lines[] = 'END;';
                $lines[] = '/';
                $lines[] = 'CREATE TABLE ' . $seedMapTable . ' AS';
                $lines[] = 'SELECT';
                $lines[] = '  tgt.' . $providerColumn . ' AS old_value,';
                $lines[] = '  ' . $seedExpr . ' AS new_value';
                $lines[] = 'FROM ' . $providerTable . ' tgt;';
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

        $qualified = $mapped['target_qualified'] ?? null;
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

        $lines[] = $this->commentDivider('=');
        $lines[] = '';

        $lines = array_merge($lines, $this->buildPrivilegePreflightSection());

        return $lines;
    }

    protected function buildPrivilegePreflightSection(): array
    {
        return [
            $this->commentDivider('='),
            '-- Privilege Preflight',
            '-- CREATE TABLE AS SELECT requires direct SELECT grants (roles are insufficient in Oracle).',
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

        $methodId = $column->pivot->anonymization_method_id ?? null;

        if ($methodId) {
            // Prefer the job-selected anonymization method from the pivot when present.
            // Fall back to the column's global method list when no pivot match is loaded.
            $resolved = $column->anonymizationMethods->firstWhere('id', $methodId);

            if ($resolved) {
                $this->methodCache[$columnId] = $resolved;
                return $resolved;
            }

            $resolved = AnonymizationMethods::withTrashed()->find($methodId);
            $this->methodCache[$columnId] = $resolved;
            return $resolved;
        }

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
