<?php

namespace App\Filament\Exports;

use App\Models\ReportEntry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;

class ReportEntryExporter extends Exporter
{

    protected static ?string $model = ReportEntry::class;

    public static function getColumns(): array
    {
        try {
            return [
                ExportColumn::make('report.name')
                    ->label('Report Name'),
                // ExportColumn::make('reportBusinessArea.name')
                //     ->label('Business Area'),
                ExportColumn::make('existing_label')
                    ->label('Existing Label'),
                // ExportColumn::make('labelSource.name')
                //     ->label('Label Source'),
                ExportColumn::make('data_field')
                    ->label('Data Field'),
                ExportColumn::make('icm_data_field_path')
                    ->label('ICM Data Field Path'),
                ExportColumn::make('data_matching_rate')
                    ->label('Data Matching Rate'),
                ExportColumn::make('note')
                    ->label('Note'),
                ExportColumn::make('created_at')
                    ->label('Created At'),
                ExportColumn::make('updated_at')
                    ->label('Updated At'),
                // ExportColumn::make('lastUpdatedBy.name')
                //     ->label('Last Updated By'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your report label export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
