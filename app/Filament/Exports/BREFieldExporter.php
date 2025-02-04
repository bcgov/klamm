<?php

namespace App\Filament\Exports;

use App\Models\BREField;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class BREFieldExporter extends Exporter
{
    protected static ?string $model = BREField::class;

    public static function getColumns(): array
    {
        try {
            return [
                ExportColumn::make('name')
                    ->label('Name'),
                ExportColumn::make('label')
                    ->label('Label'),
                ExportColumn::make('description')
                    ->label('Description'),
                ExportColumn::make('breDataType.name')
                    ->label('Data Type'),
                ExportColumn::make('breDataValidation.name')
                    ->label('Data Validation'),
                ExportColumn::make('childFields')
                    ->label('Child Rule Fields')
                    ->state(function ($record) {
                        return $record->childFields->pluck('name')->join(', ');
                    }),
                ExportColumn::make('fieldGroupNames')
                    ->label('Field Groups'),
                ExportColumn::make('input_output_type')
                    ->label('Input/Output Type')
                    ->state(function ($record) {
                        return $record->getInputOutputType();
                    }),
                ExportColumn::make('breInputs')
                    ->label('Rules: Inputs')
                    ->state(function ($record) {
                        return $record->breInputs->pluck('name')->join(', ');
                    }),
                ExportColumn::make('breOutputs')
                    ->label('Rules: Outputs')
                    ->state(function ($record) {
                        return $record->breOutputs->pluck('name')->join(', ');
                    }),
                ExportColumn::make('icmcdwFields')
                    ->label('Related ICM CDW Fields')
                    ->state(function ($record) {
                        return $record->icmcdwFields->pluck('name')->join(', ');
                    }),
                ExportColumn::make('siebelBusinessObjects')
                    ->label('Related Siebel Business Objects')
                    ->state(function ($record) {
                        return $record->siebelBusinessObjects->pluck('name')->join(', ');
                    }),
                ExportColumn::make('siebelBusinessComponents')
                    ->label('Related Siebel Business Components')
                    ->state(function ($record) {
                        return $record->siebelBusinessComponents->pluck('name')->join(', ');
                    }),
                ExportColumn::make('created_at')
                    ->label('Created At')
                    ->state(function ($record) {
                        return $record->created_at->format('Y-m-d H:i:s');
                    }),
                ExportColumn::make('updated_at')
                    ->label('Updated At')
                    ->state(function ($record) {
                        return $record->updated_at->format('Y-m-d H:i:s');
                    }),
            ];
        } catch (\Exception $e) {
            Log::error('BREFieldExporter failed to get columns: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your field export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
