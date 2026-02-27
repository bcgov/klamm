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

    public function mount(int|string $record): void
    {
        // Resolve the record through the resource's getEloquentQuery() which
        // excludes the potentially 50+ MB sql_script column.
        $this->record = AnonymizationJobResource::getEloquentQuery()
            ->findOrFail($record);

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
                ->url(fn() => AnonymizationJobResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            AnonymizationJobResource::getUrl() => 'Jobs',
            AnonymizationJobResource::getUrl('view', ['record' => $this->record]) => $this->record->name,
            '#' => 'Selection',
        ];
    }
}
