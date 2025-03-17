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
                ExportColumn::make('report_id')
                    ->label('Report ID'),
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
