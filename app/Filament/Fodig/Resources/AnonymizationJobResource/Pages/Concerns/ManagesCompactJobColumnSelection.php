<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\Concerns;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use App\Models\Anonymizer\AnonymizationJobs;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait ManagesCompactJobColumnSelection
{
    public int $jobColumnsPage = 1;

    public string $jobColumnsSearch = '';

    public ?int $jobColumnsTotal = null;

    public function usesCompactColumnSelection(): bool
    {
        $total = $this->getJobColumnsTotal();

        return $total > AnonymizationJobResource::MAX_COLUMNS_FORM_HYDRATE;
    }

    public function getJobColumnsTotal(): int
    {
        if ($this->jobColumnsTotal !== null) {
            return $this->jobColumnsTotal;
        }

        $jobId = (int) ($this->record?->getKey() ?? 0);

        $this->jobColumnsTotal = $jobId > 0
            ? AnonymizationJobResource::countJobSelectedColumns($jobId)
            : 0;

        return $this->jobColumnsTotal;
    }

    public function refreshJobColumnsTotal(): void
    {
        $this->jobColumnsTotal = null;
        $this->getJobColumnsTotal();
    }

    public function getPaginatedJobColumnsProperty(): LengthAwarePaginator
    {
        $jobId = (int) $this->record->getKey();
        $perPage = AnonymizationJobResource::JOB_COLUMNS_PER_PAGE;

        $query = DB::table('anonymization_job_columns as ajc')
            ->join('anonymous_siebel_columns as columns', 'columns.id', '=', 'ajc.column_id')
            ->join('anonymous_siebel_tables as tables', 'tables.id', '=', 'columns.table_id')
            ->join('anonymous_siebel_schemas as schemas', 'schemas.id', '=', 'tables.schema_id')
            ->join('anonymous_siebel_databases as databases', 'databases.id', '=', 'schemas.database_id')
            ->leftJoin('anonymization_methods as methods', 'methods.id', '=', 'ajc.anonymization_method_id')
            ->where('ajc.job_id', $jobId)
            ->select([
                'ajc.column_id',
                'columns.column_name',
                'tables.table_name',
                'schemas.schema_name',
                'databases.database_name',
                'methods.name as method_name',
            ])
            ->orderBy('databases.database_name')
            ->orderBy('schemas.schema_name')
            ->orderBy('tables.table_name')
            ->orderBy('columns.column_name');

        $search = trim($this->jobColumnsSearch);

        if ($search !== '') {
            $needle = '%' . mb_strtolower($search) . '%';

            $query->where(function ($q) use ($needle): void {
                $q->whereRaw('LOWER(columns.column_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(tables.table_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(schemas.schema_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(databases.database_name) LIKE ?', [$needle]);
            });
        }

        return $query->paginate($perPage, ['*'], 'jobColumnsPage', $this->jobColumnsPage);
    }

    public function updatedJobColumnsSearch(): void
    {
        $this->jobColumnsPage = 1;
    }

    public function goToJobColumnsPage(int $page): void
    {
        $this->jobColumnsPage = max(1, $page);
    }

    public function removeJobColumn(int $columnId): void
    {
        if ($columnId <= 0) {
            return;
        }

        $jobId = (int) $this->record->getKey();

        DB::table('anonymization_job_columns')
            ->where('job_id', $jobId)
            ->where('column_id', $columnId)
            ->delete();

        $this->refreshJobColumnsTotal();
        $this->resetSqlPreviewAfterColumnMutation();
        GenerateAnonymizationJobSql::dispatch($jobId);
    }

    public function addJobColumns(array $columnIds): void
    {
        $columnIds = array_values(array_filter(array_map('intval', $columnIds), fn(int $id) => $id > 0));

        if ($columnIds === []) {
            return;
        }

        $jobId = (int) $this->record->getKey();
        $existing = DB::table('anonymization_job_columns')
            ->where('job_id', $jobId)
            ->pluck('column_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $toInsert = array_values(array_diff($columnIds, $existing));

        if ($toInsert === []) {
            return;
        }

        $timestamp = now()->toDateTimeString();
        $rows = array_map(fn(int $columnId) => [
            'job_id' => $jobId,
            'column_id' => $columnId,
            'anonymization_method_id' => null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $toInsert);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('anonymization_job_columns')->insert($chunk);
        }

        $this->refreshJobColumnsTotal();
        $this->resetSqlPreviewAfterColumnMutation();
        GenerateAnonymizationJobSql::dispatch($jobId);

        $this->form->fill([
            'columns_to_add' => [],
        ]);
    }

    public function applyColumnBuilderModeForCompactJob(?string $mode): void
    {
        if ($mode === null || ! $this->record instanceof AnonymizationJobs) {
            return;
        }

        $state = $this->form->getState();
        $scope = [
            'databases' => self::sanitizeScopeIds($state['databases'] ?? []),
            'schemas' => self::sanitizeScopeIds($state['schemas'] ?? []),
            'tables' => self::sanitizeScopeIds($state['tables'] ?? []),
        ];

        if (AnonymizationJobResource::isEntireScopeMode($mode)) {
            AnonymizationJobResource::syncEntireScopeSelectionForJob($this->record, $scope);
            $this->refreshJobColumnsTotal();
            $this->resetSqlPreviewAfterColumnMutation();

            return;
        }

        if ($mode === AnonymizationJobResource::COLUMN_MODE_MANUAL) {
            return;
        }

        AnonymizationJobResource::syncJobColumnsForMode($this->record, $mode, $scope);
        $this->refreshJobColumnsTotal();
        $this->resetSqlPreviewAfterColumnMutation();
    }

    protected function resetSqlPreviewAfterColumnMutation(): void
    {
        $preview = AnonymizationJobResource::loadSqlPreview((int) $this->record->getKey());

        $this->form->fill([
            '_sql_preview_loaded' => true,
            'sql_script' => $preview,
            'sql_script_preview' => $preview !== ''
                ? $preview
                : '-- No generated SQL yet. Save or regenerate to refresh.',
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $this->refreshJobColumnsTotal();

        if ($this->usesCompactColumnSelection()) {
            unset($data['columns']);
        }

        return $data;
    }

    /**
     * @param  mixed  $value
     * @return array<int, int>
     */
    protected static function sanitizeScopeIds(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', Arr::wrap($value)), fn(int $id) => $id > 0));
    }
}
