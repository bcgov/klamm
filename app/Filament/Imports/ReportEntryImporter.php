<?php

namespace App\Filament\Imports;

use App\Models\ReportEntry;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class ReportEntryImporter extends Importer
{
    protected static ?string $model = ReportEntry::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('business_area_id')
                ->numeric()
                ->rules(['integer']),
            ImportColumn::make('report')
                ->relationship(),
            ImportColumn::make('existing_label')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('labelSource')
                ->relationship(),
            ImportColumn::make('data_field')
                ->rules(['max:255']),
            ImportColumn::make('icm_data_field_path')
                ->rules(['max:255']),
            ImportColumn::make('data_matching_rate')
                ->rules(['max:255']),
            ImportColumn::make('note'),
            ImportColumn::make('last_updated_by')
                ->numeric()
                ->rules(['integer']),
        ];
    }

    public function resolveRecord(): ?ReportEntry
    {
        // return ReportEntry::firstOrNew([
        //     // Update existing records, matching them by `$this->data['column_name']`
        //     'email' => $this->data['email'],
        // ]);

        return new ReportEntry();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your report entry import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
