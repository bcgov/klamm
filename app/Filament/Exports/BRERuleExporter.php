<?php

namespace App\Filament\Exports;

use App\Models\BRERule;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Builder;

class BRERuleExporter extends Exporter
{
    protected static ?string $model = BRERule::class;

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
                ExportColumn::make('internal_description')
                    ->label('Internal Description'),
                ExportColumn::make('breInputs')
                    ->label('Inputs')
                    ->state(function ($record) {
                        return $record->breInputs->pluck('name')->join(', ');
                    }),
                ExportColumn::make('breOutputs')
                    ->label('Outputs')
                    ->state(function ($record) {
                        return $record->breOutputs->pluck('name')->join(', ');
                    }),
                ExportColumn::make('parentRules')
                    ->label('Parent Rules')
                    ->state(function ($record) {
                        return $record->parentRules->pluck('name')->join(', ');
                    }),
                ExportColumn::make('childRules')
                    ->label('Child Rules')
                    ->state(function ($record) {
                        return $record->childRules->pluck('name')->join(', ');
                    }),
                ExportColumn::make('related_icm_cdw_fields')
                    ->label('ICM CDW Fields used')
                    ->state(function ($record) {
                        return $record->getRelatedIcmCDWFields();
                    }),
                ExportColumn::make('input_siebel_business_objects')
                    ->label('Input Siebel Business Objects')
                    ->state(function ($record) {
                        return $record->getSiebelBusinessObjects('inputs')->pluck('name')->join(', ');
                    }),
                ExportColumn::make('output_siebel_business_objects')
                    ->label('Output Siebel Business Objects')
                    ->state(function ($record) {
                        return $record->getSiebelBusinessObjects('outputs')->pluck('name')->join(', ');
                    }),
                ExportColumn::make('input_siebel_business_components')
                    ->label('Input Siebel Business Components')
                    ->state(function ($record) {
                        return $record->getSiebelBusinessComponents('inputs')->pluck('name')->join(', ');
                    }),
                ExportColumn::make('output_siebel_business_components')
                    ->label('Output Siebel Business Components')
                    ->state(function ($record) {
                        return $record->getSiebelBusinessComponents('outputs')->pluck('name')->join(', ');
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
            Log::error('BRERuleExporter failed to get columns: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your rule export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
