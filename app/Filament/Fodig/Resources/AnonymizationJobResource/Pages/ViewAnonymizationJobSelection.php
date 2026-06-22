<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Models\Anonymizer\AnonymizationJobs;
use Filament\Actions;
use Filament\Resources\Pages\Page;

class ViewAnonymizationJobSelection extends Page
{
    // Read-only job details page showing the scope/selection for an anonymization job.

    protected static string $resource = AnonymizationJobResource::class;

    protected static string $view = 'filament.fodig.resources.anonymization-job-resource.pages.view-selection';

    public AnonymizationJobs $record;

    public function mount(mixed $record): void
    {
        // The route may provide the numeric id, a model instance, an array,
        // or (occasionally) a JSON-encoded string of the record. Normalize
        // those inputs to a numeric id before resolving via the resource
        // query which excludes the potentially large `sql_script` column.
        $resolvedId = null;

        if ($record instanceof AnonymizationJobs) {
            $this->record = $record;
        } else {
            if (is_numeric($record)) {
                $resolvedId = (int) $record;
            } elseif (is_string($record)) {
                $decoded = json_decode($record, true);
                if (is_array($decoded) && isset($decoded['id']) && is_numeric($decoded['id'])) {
                    $resolvedId = (int) $decoded['id'];
                }
            } elseif (is_array($record) && isset($record['id']) && is_numeric($record['id'])) {
                $resolvedId = (int) $record['id'];
            } elseif (is_object($record) && property_exists($record, 'id') && is_numeric($record->id)) {
                $resolvedId = (int) $record->id;
            }

            if (isset($resolvedId)) {
                $this->record = AnonymizationJobResource::getEloquentQuery()
                    ->findOrFail($resolvedId);
            } else {
                // Fallback: attempt to resolve directly; let the framework
                // throw a clear exception if it cannot.
                $this->record = AnonymizationJobResource::getEloquentQuery()
                    ->findOrFail($record);
            }
        }

        $this->record->load([
            'databases.schemas',
            'schemas' => fn($query) => $query->with(['database', 'tables']),
            'tables' => fn($query) => $query
                ->with(['schema.database'])
                ->withCount('columns'),
            'columns.table.schema',
            'columns.dataType',
            'columns.anonymizationMethods',
        ]);

        $this->heading = $this->record->name . ' — Selection Details';
        $this->subheading = 'Review the target scope for this anonymization job.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('Back to Job')
                ->icon('heroicon-o-arrow-long-left')
                ->color('secondary')
                ->outlined()
                ->url(fn() => AnonymizationJobResource::getUrl('view', ['record' => $this->record->getKey()])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            AnonymizationJobResource::getUrl() => 'Jobs',
            AnonymizationJobResource::getUrl('view', ['record' => $this->record->getKey()]) => $this->record->name,
            '#' => 'Selection',
        ];
    }
}
