<?php

namespace App\Services\Anonymizer\Concerns;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait ResolvesAnonymizationColumnDependencies
{
    /**
     * @return array<int, array<string, mixed>>
     */
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

    /**
     * @return array<int, array<string, mixed>>
     */
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

    protected function findParentRowIdColumn(array $relationship): ?AnonymousSiebelColumn
    {
        $schemaRef = strtoupper(trim((string) ($relationship['schema'] ?? '')));
        $tableName = strtoupper(trim((string) ($relationship['table'] ?? '')));
        $parentColumn = strtoupper(trim((string) ($relationship['column'] ?? 'ROW_ID')));

        if ($schemaRef === '' || $tableName === '' || $parentColumn !== 'ROW_ID') {
            return null;
        }

        $schemaCandidates = $this->schemaLookupCandidates($schemaRef);

        $table = AnonymousSiebelTable::query()
            ->select('anonymous_siebel_tables.id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'anonymous_siebel_tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id')
            ->whereRaw('UPPER(anonymous_siebel_tables.table_name) = ?', [$tableName])
            ->where(function ($query) use ($schemaCandidates, $schemaRef) {
                $query->whereIn(DB::raw('UPPER(schemas.schema_name)'), $schemaCandidates);

                if (str_contains($schemaRef, '.')) {
                    $query->orWhereRaw(
                        "UPPER(databases.database_name || '.' || schemas.schema_name) = ?",
                        [$schemaRef]
                    );
                }
            })
            ->first();

        if (! $table) {
            return null;
        }

        return AnonymousSiebelColumn::query()
            ->with(['table.schema.database'])
            ->where('table_id', (int) $table->id)
            ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
            ->first();
    }

    /**
     * @return array<int, string>
     */
    protected function schemaLookupCandidates(string $schemaRef): array
    {
        $schemaRef = strtoupper(trim($schemaRef));
        if ($schemaRef === '') {
            return [];
        }

        $candidates = [$schemaRef];
        if (str_contains($schemaRef, '.')) {
            $parts = array_values(array_filter(explode('.', $schemaRef)));
            $candidates[] = (string) end($parts);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @param  array<int, bool>  $selectedIds
     * @return array<int, array<string, mixed>>
     */
    protected function requiredParentProvidersForColumn(
        AnonymousSiebelColumn $column,
        array $selectedIds
    ): array {
        $requirements = [];
        $childLabel = $this->qualifiedDependencyColumnName($column);

        $parents = $column->getRelationValue('parentColumns');
        if ($parents instanceof Collection) {
            foreach ($parents as $parent) {
                if (! $parent instanceof AnonymousSiebelColumn) {
                    continue;
                }

                $mandatory = (bool) ($parent->pivot->is_seed_mandatory ?? true);
                if (! $mandatory) {
                    continue;
                }

                $provider = $this->resolveSeedProviderColumn($parent);
                if (! $provider instanceof AnonymousSiebelColumn) {
                    continue;
                }

                $providerId = (int) $provider->getKey();
                if ($providerId <= 0 || isset($selectedIds[$providerId])) {
                    continue;
                }

                $requirements[$providerId] = [
                    'column_id' => $providerId,
                    'table_id' => (int) ($provider->table_id ?? 0),
                    'column' => $this->qualifiedDependencyColumnName($provider),
                    'reason' => 'parent_pivot',
                    'required_by' => $childLabel,
                ];
            }
        }

        foreach ($this->resolveForeignKeyRelationships($column) as $relationship) {
            if (strtoupper((string) ($relationship['direction'] ?? 'OUTBOUND')) !== 'OUTBOUND') {
                continue;
            }

            if (strtoupper(trim((string) ($relationship['column'] ?? 'ROW_ID'))) !== 'ROW_ID') {
                continue;
            }

            $parent = $this->findParentRowIdColumn($relationship);
            if (! $parent instanceof AnonymousSiebelColumn) {
                continue;
            }

            $parentId = (int) $parent->getKey();
            if ($parentId <= 0 || isset($selectedIds[$parentId])) {
                continue;
            }

            $requirements[$parentId] = [
                'column_id' => $parentId,
                'table_id' => (int) ($parent->table_id ?? 0),
                'column' => $this->qualifiedDependencyColumnName($parent),
                'reason' => 'parent_fk',
                'required_by' => $childLabel,
            ];
        }

        return $requirements;
    }

    protected function resolveSeedProviderColumn(AnonymousSiebelColumn $column): ?AnonymousSiebelColumn
    {
        if (strtoupper(trim((string) ($column->column_name ?? ''))) === 'ROW_ID') {
            return $column;
        }

        $tableId = (int) ($column->table_id ?? 0);
        if ($tableId <= 0) {
            return null;
        }

        return AnonymousSiebelColumn::query()
            ->with(['table.schema.database'])
            ->where('table_id', $tableId)
            ->whereRaw('UPPER(column_name) = ?', ['ROW_ID'])
            ->first();
    }

    protected function qualifiedDependencyColumnName(AnonymousSiebelColumn $column): string
    {
        $table = $column->getRelationValue('table');
        $schema = $table?->getRelationValue('schema');
        $database = $schema?->getRelationValue('database');

        return implode('.', array_filter([
            (string) ($database?->database_name ?? ''),
            (string) ($schema?->schema_name ?? ''),
            (string) ($table?->table_name ?? ''),
            (string) ($column->column_name ?? ''),
        ]));
    }

    protected function relationshipLabel(array $relationship): string
    {
        return implode('.', array_filter([
            (string) ($relationship['schema'] ?? ''),
            (string) ($relationship['table'] ?? ''),
            (string) ($relationship['column'] ?? 'ROW_ID'),
        ]));
    }
}
