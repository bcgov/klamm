<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages\Concerns;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Filament\Fodig\Resources\AnonymizationJobResource\Support\AnonymizationJobReadinessHelper;
use App\Jobs\GenerateAnonymizationJobSql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait SyncsAnonymizationJobSelection
{
    /**
     * Reconcile the in-form scope/column selections with the persisted job and enqueue SQL regeneration.
     *
     * @param  Model  $jobRecord
     * @param  array<string, mixed>  $state
     */
    protected function syncSelectionAndQueueSql(Model $jobRecord, array $state): void
    {
        $mode = $state['column_builder_mode'] ?? null;
        $columns = $state['columns'] ?? [];
        $scope = [
            'databases' => $state['databases'] ?? [],
            'schemas' => $state['schemas'] ?? [],
            'tables' => $state['tables'] ?? [],
        ];

        if (AnonymizationJobReadinessHelper::isEntireScopeSelection($mode, $columns, $scope)) {
            AnonymizationJobResource::syncEntireScopeSelectionForJob($jobRecord, $scope);
        }

        GenerateAnonymizationJobSql::dispatch($jobRecord->getKey());
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
