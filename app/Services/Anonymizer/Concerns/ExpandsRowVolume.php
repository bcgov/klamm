<?php

namespace App\Services\Anonymizer\Concerns;

use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Illuminate\Support\Facades\DB;

/**
 * Adds an optional, per-table "order of magnitude" row multiplier to generated
 * anonymization scripts.
 *
 * The strategy:
 *  - A user assigns a multiplier (factor N) to one or more anchor tables.
 *  - The factor propagates DOWN the dependency graph to dependent (child) tables
 *    so the whole subgraph grows proportionally; referenced ancestor tables stay
 *    at their original size.
 *  - During table cloning we cross join a deterministic row generator
 *    (CONNECT BY LEVEL) so each source row is emitted N times (dup_n = 1..N).
 *  - ROW_ID primary keys and foreign keys pointing at scaled tables are rewritten
 *    with a deterministic STANDARD_HASH derivation keyed on dup_n so primary keys
 *    stay unique and child copies line up with the matching parent copy.
 *
 * Everything is gated behind a factor > 1; with the default (1x) the generated
 * SQL is byte-for-byte identical to the pre-feature output.
 */
trait ExpandsRowVolume
{
    // Alias of the deterministic row generator added to multiplied CTAS/view statements.
    protected const ROW_GENERATOR_ALIAS = 'gen';

    // Per-copy counter column emitted by the row generator (1..N).
    protected const ROW_GENERATOR_COLUMN = 'dup_n';

    // Table-id batch size for column lookups (mirrors the host service default).
    protected const VOLUME_TABLE_CHUNK_SIZE = 100;

    public const VOLUME_MODE_MULTIPLIER = 'multiplier';

    public const VOLUME_MODE_TARGET = 'target';

    public const VOLUME_DIRECTION_EXPAND = 'expand';

    public const VOLUME_DIRECTION_REDUCE = 'reduce';

    public const REDUCTION_STRATEGY_HASH = 'deterministic_hash';

    protected const MAX_ROW_MULTIPLIER = 1000;

    protected const MAX_TARGET_ROW_COUNT = 100_000_000;

    protected const REDUCTION_HASH_BUCKETS = 1_000_000;

    /**
     * Resolve effective row multipliers for every table in scope.
     *
     * @param  array<int, array<string, mixed>>  $tablesById  rewrite-context table map
     * @return array{
     *     multipliers: array<int, int>,
     *     scaled_by_identity: array<string, array{factor:int, rowid_len:int, key:string}>,
     *     partial_sizing: array<int, array<string, mixed>>,
     *     volume_anchors: array<int, array<string, mixed>>
     * }
     */
    protected function resolveRowMultipliersForScope(?AnonymizationJobs $job, array $tablesById): array
    {
        $empty = ['multipliers' => [], 'scaled_by_identity' => [], 'partial_sizing' => [], 'volume_anchors' => []];

        if (! $job?->id || $tablesById === []) {
            return $empty;
        }

        $scopeIds = array_values(array_filter(array_map('intval', array_keys($tablesById)), fn($id) => $id > 0));
        if ($scopeIds === []) {
            return $empty;
        }

        $anchorConfigs = $this->loadVolumeAnchorConfigs((int) $job->id, $scopeIds);

        // Opt-in: with no configured anchors there is nothing to do (and no cost).
        if ($anchorConfigs === []) {
            return $empty;
        }

        $sourceRowCounts = $this->resolveTableSourceRowCounts($scopeIds);
        $anchorFactors = $this->resolveAnchorFactors($anchorConfigs, $sourceRowCounts);

        $childrenByParent = $this->buildDependentTableEdges($scopeIds);

        $effective = $anchorFactors !== []
            ? $this->propagateRowMultipliers($anchorFactors, $childrenByParent)
            : [];

        $partialAnchors = $this->resolveAnchorReductions($anchorConfigs, $sourceRowCounts);
        $partialSizing = $partialAnchors !== []
            ? $this->propagateRowReductions($partialAnchors, $childrenByParent, $this->buildParentTableEdges($childrenByParent))
            : [];

        if ($effective === [] && $partialSizing === []) {
            return $empty;
        }

        $scaledByIdentity = $this->buildScaledTableIdentityMap($effective, $tablesById);

        return [
            'multipliers' => $effective,
            'scaled_by_identity' => $scaledByIdentity,
            'partial_sizing' => $partialSizing,
            'volume_anchors' => $this->buildVolumeAnchorSummary($anchorConfigs, $anchorFactors, $sourceRowCounts),
        ];
    }

    /**
     * @param  array<int, int>  $scopeIds
     * @return array<int, array{mode:string, row_multiplier:int, target_row_count:?int, direction:string, reduction_strategy:?string}>
     */
    protected function loadVolumeAnchorConfigs(int $jobId, array $scopeIds): array
    {
        $rows = DB::table('anonymization_job_tables')
            ->where('job_id', $jobId)
            ->whereIn('table_id', $scopeIds)
            ->get(['table_id', 'row_multiplier', 'volume_mode', 'target_row_count', 'volume_direction', 'reduction_strategy']);

        $configs = [];

        foreach ($rows as $row) {
            $tableId = (int) $row->table_id;
            $mode = strtolower(trim((string) ($row->volume_mode ?? self::VOLUME_MODE_MULTIPLIER)));
            $direction = strtolower(trim((string) ($row->volume_direction ?? self::VOLUME_DIRECTION_EXPAND)));
            if (! in_array($direction, [self::VOLUME_DIRECTION_EXPAND, self::VOLUME_DIRECTION_REDUCE], true)) {
                $direction = self::VOLUME_DIRECTION_EXPAND;
            }

            $multiplier = max(1, (int) ($row->row_multiplier ?? 1));
            $target = (int) ($row->target_row_count ?? 0);

            if ($direction === self::VOLUME_DIRECTION_REDUCE && $target > 0) {
                $configs[$tableId] = [
                    'mode' => self::VOLUME_MODE_TARGET,
                    'row_multiplier' => 1,
                    'target_row_count' => min(self::MAX_TARGET_ROW_COUNT, $target),
                    'direction' => self::VOLUME_DIRECTION_REDUCE,
                    'reduction_strategy' => self::REDUCTION_STRATEGY_HASH,
                ];

                continue;
            }

            if ($mode === self::VOLUME_MODE_TARGET && $target > 0) {
                $configs[$tableId] = [
                    'mode' => self::VOLUME_MODE_TARGET,
                    'row_multiplier' => 1,
                    'target_row_count' => min(self::MAX_TARGET_ROW_COUNT, $target),
                    'direction' => self::VOLUME_DIRECTION_EXPAND,
                    'reduction_strategy' => null,
                ];

                continue;
            }

            if ($multiplier > 1) {
                $configs[$tableId] = [
                    'mode' => self::VOLUME_MODE_MULTIPLIER,
                    'row_multiplier' => min(self::MAX_ROW_MULTIPLIER, $multiplier),
                    'target_row_count' => null,
                    'direction' => self::VOLUME_DIRECTION_EXPAND,
                    'reduction_strategy' => null,
                ];
            }
        }

        return $configs;
    }

    /**
     * Catalog row counts for tables, preferring ROW_ID column statistics when present.
     *
     * @param  array<int, int>  $tableIds
     * @return array<int, int>
     */
    protected function resolveTableSourceRowCounts(array $tableIds): array
    {
        $tableIds = array_values(array_unique(array_filter(array_map('intval', $tableIds), fn($id) => $id > 0)));
        if ($tableIds === []) {
            return [];
        }

        $counts = [];

        foreach (array_chunk($tableIds, self::VOLUME_TABLE_CHUNK_SIZE * 5) as $chunk) {
            $rows = AnonymousSiebelColumn::query()
                ->whereIn('table_id', $chunk)
                ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
                ->get(['table_id', 'num_rows']);

            foreach ($rows as $row) {
                $tableId = (int) $row->table_id;
                $numRows = (int) ($row->num_rows ?? 0);
                if ($numRows > 0) {
                    $counts[$tableId] = max($counts[$tableId] ?? 0, $numRows);
                }
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array{mode:string, row_multiplier:int, target_row_count:?int, direction?:string}>  $anchorConfigs
     * @param  array<int, int>  $sourceRowCounts
     * @return array<int, int>  table_id => effective factor (>1 only)
     */
    protected function resolveAnchorFactors(array $anchorConfigs, array $sourceRowCounts): array
    {
        $factors = [];

        foreach ($anchorConfigs as $tableId => $config) {
            $factor = $this->resolveEffectiveFactorFromAnchor($config, (int) ($sourceRowCounts[$tableId] ?? 0));
            if ($factor > 1) {
                $factors[(int) $tableId] = $factor;
            }
        }

        return $factors;
    }

    /**
     * @param  array{mode:string, row_multiplier:int, target_row_count:?int, direction?:string}  $config
     */
    protected function resolveEffectiveFactorFromAnchor(array $config, int $sourceRows): int
    {
        if (($config['direction'] ?? self::VOLUME_DIRECTION_EXPAND) === self::VOLUME_DIRECTION_REDUCE) {
            return 1;
        }

        $mode = strtolower(trim((string) ($config['mode'] ?? self::VOLUME_MODE_MULTIPLIER)));

        if ($mode === self::VOLUME_MODE_TARGET) {
            $target = (int) ($config['target_row_count'] ?? 0);
            if ($target <= 0) {
                return 1;
            }

            $target = min(self::MAX_TARGET_ROW_COUNT, $target);
            $source = max(1, $sourceRows);

            // Catalog statistics are only an estimate. Keep target anchors active
            // so generated SQL can choose the final factor from the live source
            // count at runtime; the join collapses to 1x when already at target.
            $factor = (int) ceil($target / $source);

            return max(2, min(self::MAX_ROW_MULTIPLIER, $factor));
        }

        return max(1, min(self::MAX_ROW_MULTIPLIER, (int) ($config['row_multiplier'] ?? 1)));
    }

    /**
     * Propagate each anchor's factor DOWN its dependent subgraph.
     *
     * Directly configured tables keep their own factor instead of inheriting a
     * parent's multiplier/target sizing. When multiple anchors reach the same
     * uncontrolled table, the largest factor wins.
     *
     * @param  array<int, int>  $anchorFactors  table_id => factor for directly configured anchors
     * @param  array<int, array<int, int>>  $childrenByParent  parent_id => [child_id, ...]
     * @return array<int, int>  table_id => effective factor (only entries > 1)
     */
    protected function propagateRowMultipliers(array $anchorFactors, array $childrenByParent): array
    {
        $effective = [];

        foreach ($anchorFactors as $rootAnchorId => $rootFactor) {
            $rootFactor = (int) $rootFactor;
            if ($rootFactor <= 1) {
                continue;
            }

            $stack = [[(int) $rootAnchorId, $rootFactor]];
            $seen = [];

            while ($stack !== []) {
                [$node, $inheritedFactor] = array_pop($stack);
                if (isset($seen[$node])) {
                    continue;
                }
                $seen[$node] = true;

                $nodeFactor = $anchorFactors[$node] ?? $inheritedFactor;
                $effective[$node] = max($effective[$node] ?? 1, $nodeFactor);

                foreach ($childrenByParent[$node] ?? [] as $child) {
                    if (! isset($seen[$child])) {
                        $stack[] = [(int) $child, $nodeFactor];
                    }
                }
            }
        }

        return array_filter($effective, fn($factor) => $factor > 1);
    }

    /**
     * @param  array<int, array{mode:string, row_multiplier:int, target_row_count:?int, direction?:string}>  $anchorConfigs
     * @param  array<int, int>  $sourceRowCounts
     * @return array<int, array<string, mixed>>
     */
    protected function resolveAnchorReductions(array $anchorConfigs, array $sourceRowCounts): array
    {
        $reductions = [];

        foreach ($anchorConfigs as $tableId => $config) {
            if (($config['direction'] ?? self::VOLUME_DIRECTION_EXPAND) !== self::VOLUME_DIRECTION_REDUCE) {
                continue;
            }

            $sourceRows = (int) ($sourceRowCounts[(int) $tableId] ?? 0);
            $targetRows = (int) ($config['target_row_count'] ?? 0);

            if ($sourceRows <= 0 || $targetRows <= 0 || $targetRows >= $sourceRows) {
                continue;
            }

            $keepPerMillion = max(1, min(
                self::REDUCTION_HASH_BUCKETS - 1,
                (int) floor(($targetRows / max(1, $sourceRows)) * self::REDUCTION_HASH_BUCKETS)
            ));

            $reductions[(int) $tableId] = [
                'direction' => self::VOLUME_DIRECTION_REDUCE,
                'strategy' => self::REDUCTION_STRATEGY_HASH,
                'target_row_count' => $targetRows,
                'source_row_count' => $sourceRows,
                'keep_per_million' => $keepPerMillion,
                'hash_buckets' => self::REDUCTION_HASH_BUCKETS,
            ];
        }

        return $reductions;
    }

    /**
     * @param  array<int, array<int, int>>  $childrenByParent
     * @return array<int, array<int, int>>
     */
    protected function buildParentTableEdges(array $childrenByParent): array
    {
        $parentsByChild = [];

        foreach ($childrenByParent as $parent => $children) {
            foreach ($children as $child) {
                $parentsByChild[(int) $child][(int) $parent] = (int) $parent;
            }
        }

        return array_map(fn(array $parents) => array_values($parents), $parentsByChild);
    }

    /**
     * Propagate reduction anchors in both directions so connected parent/child
     * tables receive the same deterministic proportional sampling directive.
     *
     * @param  array<int, array<string, mixed>>  $anchorReductions
     * @param  array<int, array<int, int>>  $childrenByParent
     * @param  array<int, array<int, int>>  $parentsByChild
     * @return array<int, array<string, mixed>>
     */
    protected function propagateRowReductions(array $anchorReductions, array $childrenByParent, array $parentsByChild): array
    {
        $effective = [];

        foreach ($anchorReductions as $rootAnchorId => $rootDirective) {
            $stack = [[(int) $rootAnchorId, $rootDirective]];
            $seen = [];

            while ($stack !== []) {
                [$node, $inheritedDirective] = array_pop($stack);
                $node = (int) $node;
                if (isset($seen[$node])) {
                    continue;
                }
                $seen[$node] = true;

                $nodeDirective = $anchorReductions[$node] ?? $inheritedDirective;
                $effective[$node] = $this->strongestRowReduction($effective[$node] ?? null, $nodeDirective);

                foreach (array_merge($childrenByParent[$node] ?? [], $parentsByChild[$node] ?? []) as $connected) {
                    if (! isset($seen[(int) $connected])) {
                        $stack[] = [(int) $connected, $nodeDirective];
                    }
                }
            }
        }

        return array_filter($effective, fn($directive) => is_array($directive) && (int) ($directive['keep_per_million'] ?? self::REDUCTION_HASH_BUCKETS) < self::REDUCTION_HASH_BUCKETS);
    }

    /**
     * @param  array<string, mixed>|null  $current
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    protected function strongestRowReduction(?array $current, array $candidate): array
    {
        if ($current === null) {
            return $candidate;
        }

        return (int) ($candidate['keep_per_million'] ?? self::REDUCTION_HASH_BUCKETS) < (int) ($current['keep_per_million'] ?? self::REDUCTION_HASH_BUCKETS)
            ? $candidate
            : $current;
    }

    /**
     * @param  array<int, array{mode:string, row_multiplier:int, target_row_count:?int}>  $anchorConfigs
     * @param  array<int, int>  $anchorFactors
     * @param  array<int, int>  $sourceRowCounts
     * @return array<int, array<string, mixed>>
     */
    protected function buildVolumeAnchorSummary(array $anchorConfigs, array $anchorFactors, array $sourceRowCounts): array
    {
        $summary = [];

        foreach ($anchorConfigs as $tableId => $config) {
            $factor = (int) ($anchorFactors[$tableId] ?? 1);
            if ($factor <= 1) {
                continue;
            }

            $summary[(int) $tableId] = [
                'mode' => $config['mode'],
                'factor' => $factor,
                'target_row_count' => $config['target_row_count'],
                'source_row_count' => $sourceRowCounts[$tableId] ?? null,
            ];
        }

        return $summary;
    }

    /**
     * Build a parent -> [children] adjacency map for the in-scope tables, where a
     * child is any table that holds a foreign key into the parent's ROW_ID.
     *
     * @param  array<int, int>  $scopeTableIds
     * @return array<int, array<int, int>>
     */
    protected function buildDependentTableEdges(array $scopeTableIds): array
    {
        $scopeTableIds = array_values(array_unique(array_filter(array_map('intval', $scopeTableIds), fn($id) => $id > 0)));
        if ($scopeTableIds === []) {
            return [];
        }

        // Identity (SCHEMA|TABLE, uppercased) -> table_id, for parent resolution.
        $identity = [];
        $tables = AnonymousSiebelTable::query()
            ->withTrashed()
            ->with('schema')
            ->whereIn('id', $scopeTableIds)
            ->get();

        foreach ($tables as $table) {
            $schema = strtoupper(trim((string) ($table->getRelationValue('schema')?->schema_name ?? '')));
            $name = strtoupper(trim((string) ($table->table_name ?? '')));
            if ($schema !== '' && $name !== '') {
                $identity[$schema . '|' . $name] = (int) $table->getKey();
            }
        }

        $childrenByParent = [];

        foreach (array_chunk($scopeTableIds, self::VOLUME_TABLE_CHUNK_SIZE) as $chunk) {
            $columns = AnonymousSiebelColumn::query()
                ->with(['table.schema'])
                ->whereIn('table_id', $chunk)
                ->get();

            foreach ($columns as $column) {
                $childTableId = (int) ($column->table_id ?? 0);
                if ($childTableId <= 0) {
                    continue;
                }

                foreach ($this->resolveForeignKeyRelationships($column) as $relationship) {
                    if (strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND')) === 'INBOUND') {
                        continue;
                    }

                    if (strtoupper(trim((string) ($relationship['column'] ?? 'ROW_ID'))) !== 'ROW_ID') {
                        continue;
                    }

                    $schema = strtoupper(trim((string) ($relationship['schema'] ?? '')));
                    $table = strtoupper(trim((string) ($relationship['table'] ?? '')));
                    if ($schema === '' || $table === '') {
                        continue;
                    }

                    $parentId = $identity[$schema . '|' . $table] ?? null;
                    if (! $parentId || $parentId === $childTableId) {
                        continue;
                    }

                    $childrenByParent[$parentId][$childTableId] = $childTableId;
                }
            }

            unset($columns);
        }

        return array_map(fn(array $children) => array_values($children), $childrenByParent);
    }

    /**
     * Build the lookup metadata used to rewrite ROW_ID / FK expressions for scaled
     * tables, keyed by uppercased SCHEMA|TABLE identity.
     *
     * @param  array<int, int>  $effective  table_id => factor (>1)
     * @param  array<int, array<string, mixed>>  $tablesById
     * @return array<string, array{factor:int, rowid_len:int, key:string}>
     */
    protected function buildScaledTableIdentityMap(array $effective, array $tablesById): array
    {
        $scaledIds = array_keys($effective);
        if ($scaledIds === []) {
            return [];
        }

        // ROW_ID widths drive the SUBSTR length so parent ROW_ID and child FK hashes
        // produce identical strings (referential integrity for generated copies).
        $rowIdLengthByTable = [];
        foreach (array_chunk($scaledIds, self::VOLUME_TABLE_CHUNK_SIZE * 5) as $chunk) {
            $rowIds = AnonymousSiebelColumn::query()
                ->whereIn('table_id', $chunk)
                ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
                ->get(['table_id', 'char_length', 'data_length']);

            foreach ($rowIds as $rowId) {
                $length = (int) ($rowId->char_length ?: $rowId->data_length ?: 15);
                $rowIdLengthByTable[(int) $rowId->table_id] = max(1, min(64, $length));
            }
        }

        $map = [];
        foreach ($effective as $tableId => $factor) {
            $mapping = $tablesById[(int) $tableId] ?? null;
            if (! is_array($mapping)) {
                continue;
            }

            $schema = strtoupper(trim((string) ($mapping['source_schema'] ?? '')));
            $table = strtoupper(trim((string) ($mapping['source_table'] ?? '')));
            if ($schema === '' || $table === '') {
                continue;
            }

            $map[$schema . '|' . $table] = [
                'factor' => (int) $factor,
                'rowid_len' => $rowIdLengthByTable[(int) $tableId] ?? 15,
                'key' => $schema . '.' . $table,
            ];
        }

        return $map;
    }

    /**
     * The deterministic row generator join appended after the source table when a
     * table is being multiplied.
     */
    protected function rowGeneratorJoinClause(int $factor): string
    {
        $factor = max(1, $factor);

        return 'CROSS JOIN (SELECT LEVEL AS ' . self::ROW_GENERATOR_COLUMN
            . ' FROM dual CONNECT BY LEVEL <= ' . $factor . ') ' . self::ROW_GENERATOR_ALIAS;
    }

    /**
     * Row generator join whose factor is chosen at runtime by counting the
     * source table and comparing it to a target row count.
     *
     * When the runtime source row count is already >= target, this collapses to 1
     * (no expansion). Otherwise it expands by CEIL(target / source_count), capped.
     */
    protected function rowGeneratorJoinClauseForTarget(string $qualifiedSource, int $targetRowCount): string
    {
        $targetRowCount = max(1, min(self::MAX_TARGET_ROW_COUNT, $targetRowCount));

        // Uncorrelated scalar subquery — Oracle will typically evaluate it once.
        $factorExpr =
            '(SELECT CASE'
            . ' WHEN cnt >= ' . $targetRowCount . ' THEN 1'
            . ' ELSE LEAST(' . self::MAX_ROW_MULTIPLIER . ', CEIL(' . $targetRowCount . ' / GREATEST(cnt, 1)))'
            . ' END'
            . ' FROM (SELECT COUNT(*) cnt FROM ' . $qualifiedSource . '))';

        return 'CROSS JOIN (SELECT LEVEL AS ' . self::ROW_GENERATOR_COLUMN
            . ' FROM dual CONNECT BY LEVEL <= ' . $factorExpr . ') ' . self::ROW_GENERATOR_ALIAS;
    }

    /**
     * @param  array<string, mixed>  $directive
     */
    protected function rowReductionWhereClause(array $directive, string $sourceAlias, string $tableKey, array $rewriteContext): string
    {
        $keepPerMillion = max(1, min(
            self::REDUCTION_HASH_BUCKETS - 1,
            (int) ($directive['keep_per_million'] ?? self::REDUCTION_HASH_BUCKETS)
        ));

        $alias = trim($sourceAlias) !== '' ? trim($sourceAlias) : 'src';
        $rowIdRef = $alias . '.' . $this->oracleIdentifier('ROW_ID');
        $jobSeedLiteral = (string) ($rewriteContext['job_seed_literal'] ?? "''");
        if (trim($jobSeedLiteral) === '') {
            $jobSeedLiteral = "''";
        }

        $hashInput = $jobSeedLiteral
            . " || '|PARTIAL|' || " . $this->oracleStringLiteral($tableKey)
            . " || '|' || NVL(" . $rowIdRef . ", '~')";

        $bucketExpr = 'MOD(TO_NUMBER(SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH('
            . $hashInput
            . ", 'SHA256'))), 1, 8), 'xxxxxxxx'), " . self::REDUCTION_HASH_BUCKETS . ')';

        return $rowIdRef . ' IS NOT NULL AND ' . $bucketExpr . ' < ' . $keepPerMillion;
    }

    /**
     * Determine whether a column needs a dup_n-aware derivation for the given
     * table factor, and return the derivation descriptor.
     *
     * @param  array<string, array{factor:int, rowid_len:int, key:string}>  $scaledByIdentity
     * @return array<string, mixed>|null
     */
    protected function resolveColumnVolumeDerivation(
        AnonymousSiebelColumn $column,
        int $tableFactor,
        array $scaledByIdentity,
        string $sourceAlias,
        ?string $ownTableIdentity = null
    ): ?array {
        if ($tableFactor <= 1 || $scaledByIdentity === []) {
            return null;
        }

        $columnName = trim((string) ($column->column_name ?? ''));
        if ($columnName === '') {
            return null;
        }

        $srcRef = $sourceAlias . '.' . $this->oracleIdentifier($columnName);
        $dupColumn = self::ROW_GENERATOR_ALIAS . '.' . self::ROW_GENERATOR_COLUMN;

        // ROW_ID primary key of a scaled table: make each copy unique.
        if (strtoupper($columnName) === 'ROW_ID') {
            if ($ownTableIdentity !== null && trim($ownTableIdentity) !== '') {
                $identity = strtoupper(trim($ownTableIdentity));
            } else {
                $schema = strtoupper(trim((string) ($column->getRelationValue('table')?->getRelationValue('schema')?->schema_name ?? '')));
                $table = strtoupper(trim((string) ($column->getRelationValue('table')?->table_name ?? '')));
                $identity = $schema . '|' . $table;
            }

            $info = $scaledByIdentity[$identity] ?? null;

            if ($info !== null) {
                return [
                    'kind' => 'rowid',
                    'src_ref' => $srcRef,
                    'parent_key' => $info['key'],
                    'hash_len' => (int) $info['rowid_len'],
                    'dup_expr' => $dupColumn,
                ];
            }

            return null;
        }

        // Foreign key into a scaled parent: align child copy dup_n with the parent copy.
        foreach ($this->resolveForeignKeyRelationships($column) as $relationship) {
            if (strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND')) === 'INBOUND') {
                continue;
            }

            if (strtoupper(trim((string) ($relationship['column'] ?? 'ROW_ID'))) !== 'ROW_ID') {
                continue;
            }

            $schema = strtoupper(trim((string) ($relationship['schema'] ?? '')));
            $table = strtoupper(trim((string) ($relationship['table'] ?? '')));
            $info = $scaledByIdentity[$schema . '|' . $table] ?? null;

            if ($info === null) {
                continue;
            }

            $parentFactor = (int) $info['factor'];

            // When the child grows faster than the parent, wrap the copy index so the
            // FK always points at an existing parent copy (1..parentFactor).
            $dupExpr = $tableFactor <= $parentFactor
                ? $dupColumn
                : 'MOD(' . $dupColumn . ' - 1, ' . $parentFactor . ') + 1';

            return [
                'kind' => 'fk',
                'src_ref' => $srcRef,
                'parent_key' => $info['key'],
                'hash_len' => (int) $info['rowid_len'],
                'dup_expr' => $dupExpr,
            ];
        }

        return null;
    }

    /**
     * Wrap a base select expression so generated copies (dup_n > 1) receive a
     * deterministic, collision-free value while the original copy (dup_n = 1)
     * keeps today's masked/passthrough value.
     *
     * @param  array<string, mixed>  $derivation
     */
    protected function wrapVolumeExpandedExpression(string $baseExpr, array $derivation, array $rewriteContext): string
    {
        $srcRef = (string) ($derivation['src_ref'] ?? '');
        $dupExpr = (string) ($derivation['dup_expr'] ?? (self::ROW_GENERATOR_ALIAS . '.' . self::ROW_GENERATOR_COLUMN));
        $hashLen = max(1, (int) ($derivation['hash_len'] ?? 15));
        $parentKey = (string) ($derivation['parent_key'] ?? '');

        $jobSeedLiteral = (string) ($rewriteContext['job_seed_literal'] ?? "''");
        if (trim($jobSeedLiteral) === '') {
            $jobSeedLiteral = "''";
        }

        $hashInput = $jobSeedLiteral
            . " || '|RID|' || " . $this->oracleStringLiteral($parentKey)
            . " || '|' || NVL(" . $srcRef . ", '~')"
            . " || '|' || TO_CHAR(" . $dupExpr . ')';

        $hashExpr = 'SUBSTR(LOWER(RAWTOHEX(STANDARD_HASH(' . $hashInput . ", 'SHA256'))), 1, " . $hashLen . ')';

        return 'CASE WHEN (' . $dupExpr . ') = 1 OR ' . $srcRef . ' IS NULL'
            . ' THEN (' . $baseExpr . ')'
            . ' ELSE ' . $hashExpr
            . ' END';
    }

    /**
     * Whether a column is a volume-derived key (ROW_ID on a scaled table, or a
     * foreign key into a scaled parent). Such columns are finalized at clone time
     * and must be skipped by the double-seeded seed-map masking so the derived
     * value is not overwritten.
     *
     * @param  array<int, int>  $rowMultipliers  table_id => factor
     * @param  array<string, array{factor:int, rowid_len:int, key:string}>  $scaledByIdentity
     */
    protected function isVolumeDerivedColumn(
        AnonymousSiebelColumn $column,
        array $rowMultipliers,
        array $scaledByIdentity
    ): bool {
        $factor = (int) ($rowMultipliers[(int) ($column->table_id ?? 0)] ?? 1);

        if ($factor <= 1 || $scaledByIdentity === []) {
            return false;
        }

        return $this->resolveColumnVolumeDerivation($column, $factor, $scaledByIdentity, 'src') !== null;
    }
}
