<?php

namespace App\Filament\Exports;

use App\Models\Form;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Facades\Log;

class FormExporter extends Exporter
{
    protected static ?string $model = Form::class;

    public static function getColumns(): array
    {
        try {
            return [
                ExportColumn::make('form_id')
                    ->label('Form ID'),
                ExportColumn::make('form_title')
                    ->label('Form Title'),
                ExportColumn::make('ministry.short_name')
                    ->label('Ministry'),
                ExportColumn::make('businessAreas.name')
                    ->label('Business Areas'),
                ExportColumn::make('program'),
                ExportColumn::make('form_purpose')
                    ->label('Form Purpose'),
                ExportColumn::make('notes'),
                ExportColumn::make('decommissioned')
                    ->label('Decommissioned')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
                ExportColumn::make('icm_generated')
                    ->label('ICM Generated')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
                ExportColumn::make('icm_non_interactive')
                    ->label('ICM Non-Interactive')
                    ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No'),
                ExportColumn::make('formSoftwareSources.name')
                    ->label('Form Software Sources'),
                ExportColumn::make('formLocations.name')
                    ->label('Form Locations'),
                ExportColumn::make('formRepositories.name')
                    ->label('Form Repositories'),
                ExportColumn::make('formTags.name')
                    ->label('Form Tags'),
                ExportColumn::make('fillType.name')
                    ->label('Fill Type'),
                ExportColumn::make('formFrequency.name')
                    ->label('Form Frequency'),
                ExportColumn::make('formReach.name')
                    ->label('Form Reach'),
                ExportColumn::make('userTypes.name')
                    ->label('User Types'),
                ExportColumn::make('print_reason')
                    ->label('Print Reason'),
                ExportColumn::make('orbeon_functions')
                    ->label('Orbeon Functions'),
                ExportColumn::make('retention_needs')
                    ->label('Retention Needs (years)'),
                ExportColumn::make('relatedForms.form_title')
                    ->label('Related Forms'),
                ExportColumn::make('footer_fragment_path')
                    ->label('Footer Fragment Path'),
                ExportColumn::make('workbenchPaths.workbench_path')
                    ->label('Workbench Paths'),
                ExportColumn::make('dcv_material_number')
                    ->label('DCV Material Number'),
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your form export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
