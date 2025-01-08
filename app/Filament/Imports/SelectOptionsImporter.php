<?php

namespace App\Filament\Imports;

use App\Models\SelectOptions;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;

class SelectOptionsImporter extends Importer
{
    protected static ?string $model = SelectOptions::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->rules(['required'])
                ->requiredMapping()
                ->example('eye_color_brown'),
            ImportColumn::make('label')
                ->example('Brown'),
            ImportColumn::make('value')
                ->example('1'),
            ImportColumn::make('description')
                ->example('This option is used for...'),
            // ImportColumn::make('form_fields')
            //     ->array(',')
            //     ->guess(['fields', 'elements', 'form_elements'])
            //     // ->rules(['required', 'exists:form_fields,id'])
            //     ->requiredMapping()
            //     ->integer()
            //     ->example('4,17')
            // ->relationship(resolveUsing: function (string $state): ?SelectOptions {
            //     $formFieldIDs = Str::of($state)->explode(',');
            //     // dd($formFieldIDs);
            //     return SelectOptions::query()
            //         ->whereHas('formFields', function ($query) use ($formFieldIDs) {
            //             $query->whereIn('id', $formFieldIDs);
            //         })
            //         ->first();
            // })
        ];
    }

    public function resolveRecord(): ?SelectOptions
    {
        return SelectOptions::firstOrNew([
            // Update existing records, matching them by `$this->data['column_name']`
            'name' => $this->data['name'],
        ]);

        return new SelectOptions();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your select options import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
