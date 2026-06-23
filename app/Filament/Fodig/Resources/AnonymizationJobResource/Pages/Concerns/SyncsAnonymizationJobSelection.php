<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\Concerns;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Support\AnonymizationJobReadinessHelper;
use App\Jobs\GenerateAnonymizationJobSql;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Services\Anonymizer\AnonymizationJobDependencyClosureService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait SyncsAnonymizationJobSelection
{
    /**
     * Snapshot of configured volume anchors captured before Filament relationship sync.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $volumeAnchorSnapshotRows = [];

    /**
     * Reconcile the in-form scope/column selections with the persisted job and enqueue SQL regeneration.
     *
     * @param  Model  $jobRecord
     * @param  array<string, mixed>  $state
     */
    protected function syncSelectionAndQueueSql(Model $jobRecord, array $state): void
    {
        if (! $jobRecord instanceof AnonymizationJobs) {
            return;
        }

        $mode = $state['column_builder_mode'] ?? null;
        $columns = $state['columns'] ?? [];

        // Get scope from form state - Filament stores relationship IDs directly in state
        $scopeFromState = [
            'databases' => $this->extractRelationshipIds($state, 'databases'),
            'schemas' => $this->extractRelationshipIds($state, 'schemas'),
            'tables' => $this->extractRelationshipIds($state, 'tables'),
        ];

        // Filament's relationship() component should sync these automatically.
        // Only manually sync if we have explicit data in state AND the relationships are empty.
        // This prevents us from accidentally wiping out what Filament already synced.
        // Clear cached relationships so the next pluck queries hit the DB.
        // Avoid $jobRecord->refresh() which would reload the 50+ MB sql_script column.
        $jobRecord->unsetRelations();

        $existingDatabases = $jobRecord->databases()->pluck('anonymous_siebel_databases.id')->all();
        $existingSchemas = $jobRecord->schemas()->pluck('anonymous_siebel_schemas.id')->all();
        $existingTables = $jobRecord->tables()->pluck('anonymous_siebel_tables.id')->all();

        // If Filament didn't sync the relationships (they're empty) but we have state data, sync manually
        $needsManualSync = (
            (empty($existingDatabases) && ! empty($scopeFromState['databases'])) ||
            (empty($existingSchemas) && ! empty($scopeFromState['schemas'])) ||
            (empty($existingTables) && ! empty($scopeFromState['tables']))
        );

        if ($needsManualSync) {
            $this->syncScopeRelationships($jobRecord, $scopeFromState);
            $jobRecord->unsetRelations();
        }

        // Use the actual persisted scope for further processing
        $scope = [
            'databases' => $jobRecord->databases()->pluck('anonymous_siebel_databases.id')->map(fn($id) => (int) $id)->all(),
            'schemas' => $jobRecord->schemas()->pluck('anonymous_siebel_schemas.id')->map(fn($id) => (int) $id)->all(),
            'tables' => $jobRecord->tables()->pluck('anonymous_siebel_tables.id')->map(fn($id) => (int) $id)->all(),
        ];

        if (AnonymizationJobReadinessHelper::isEntireScopeSelection($mode, $columns, $scope)) {
            AnonymizationJobResource::syncEntireScopeSelectionForJob($jobRecord, $scope);
        }

        // Persist per-table volume multipliers last so they survive scope sync.
        // The repeater is dehydrated(false); only persist when raw form state is present.
        $volumeRows = AnonymizationJobResource::volumeMultiplierRowsFromForm($this);
        if ($volumeRows === null && $this->volumeAnchorSnapshotRows !== []) {
            $volumeRows = $this->volumeAnchorSnapshotRows;
        }
        if ($volumeRows !== null) {
            AnonymizationJobResource::persistTableMultipliers($jobRecord, $volumeRows);
        }

        $dependencyClosureService = app(AnonymizationJobDependencyClosureService::class);
        $dependencyClosureService->applyForJob($jobRecord, [
            'partial_uses_existing_full_anonymization' => (bool) ($state['partial_uses_existing_full_anonymization'] ?? false),
            'partial_baseline_reference' => trim((string) ($state['partial_baseline_reference'] ?? '')),
        ]);
        $dependencyClosureService->syncJobDependencyAttributesFromDatabase($jobRecord);

        $jobRecord->unsetRelations();

        GenerateAnonymizationJobSql::dispatch($jobRecord->getKey());
    }

    /**
     * Extract relationship IDs from form state, handling various formats Filament might use.
     */
    protected function extractRelationshipIds(array $state, string $key): array
    {
        $value = $state[$key] ?? [];

        // Handle null or empty
        if (empty($value)) {
            return [];
        }

        // Already an array of IDs
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value), fn($id) => $id > 0));
        }

        // Single ID
        if (is_numeric($value)) {
            return [(int) $value];
        }

        return [];
    }

    /**
     * Capture configured volume anchors before Filament syncs the tables relationship.
     */
    protected function captureVolumeAnchorSnapshot(AnonymizationJobs $job): void
    {
        $this->volumeAnchorSnapshotRows = AnonymizationJobResource::storedVolumeAnchorRowsForJob((int) $job->getKey());
    }

    /**
     * Explicitly sync scope relationships (databases, schemas, tables) to ensure they're persisted.
     */
    protected function syncScopeRelationships(AnonymizationJobs $job, array $scope): void
    {
        $databaseIds = array_values(array_filter(array_map('intval', Arr::wrap($scope['databases'] ?? []))));
        $schemaIds = array_values(array_filter(array_map('intval', Arr::wrap($scope['schemas'] ?? []))));
        $tableIds = array_values(array_filter(array_map('intval', Arr::wrap($scope['tables'] ?? []))));

        $job->databases()->sync($databaseIds);
        $job->schemas()->sync($schemaIds);

        $tableSync = AnonymizationJobResource::buildTableSyncPayloadPreservingVolume(
            (int) $job->getKey(),
            $tableIds
        );

        if ($tableSync !== []) {
            $job->tables()->sync($tableSync);
        } else {
            $job->tables()->sync([]);
        }
    }

    // Generate a report of the job details in md format for download.
    // Allows for reviewing/cataloguing readiness of job details in external format.
    protected function downloadReadinessReportFromForm(?Model $jobRecord = null): StreamedResponse
    {
        $state = $this->form->getState();

        // Prefer persisted job selection when a job exists.
        if ($jobRecord?->exists) {
            $report = app(\App\Services\Anonymizer\AnonymizationJobReadinessService::class)
                ->reportForJob((int) $jobRecord->getKey());
        } else {
            $mode = $state['column_builder_mode'] ?? null;
            $columns = $state['columns'] ?? [];
            $scope = [
                'databases' => $state['databases'] ?? [],
                'schemas' => $state['schemas'] ?? [],
                'tables' => $state['tables'] ?? [],
            ];
            $jobId = $jobRecord?->exists ? (int) $jobRecord->getKey() : null;

            $report = AnonymizationJobReadinessHelper::reportForSelection($mode, $columns, $scope, $jobId);
        }

        $timestamp = now()->format('Ymd_His');
        $name = Str::slug((string) ($state['name'] ?? $jobRecord?->name ?? 'anonymization-job')) ?: 'anonymization-job';
        $filename = $timestamp . '_' . $name . '_readiness.md';
        $payload = (string) ($report['markdown'] ?? '');

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
