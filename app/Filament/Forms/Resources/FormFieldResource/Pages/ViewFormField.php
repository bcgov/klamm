<?php

namespace App\Filament\Forms\Resources\FormFieldResource\Pages;

use App\Filament\Forms\Resources\FormFieldResource;
use App\Helpers\DateFormatHelper;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFormField extends ViewRecord
{
    protected static string $resource = FormFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = array_merge($this->record->toArray(), $data);

        $formFieldValueObj = $this->record->formFieldValue()->first();
        $data['value'] = $formFieldValueObj?->value;

        $dateFormatObj = $this->record->formFieldDateFormat()->first();
        $data['date_format'] = array_search($dateFormatObj?->date_format, DateFormatHelper::dateFormats());

        $data['selectable_value_instances'] = $this->record->selectableValueInstances->map(fn($instance) => [
            'type' => 'selectable_value_instance',
            'data' => [
                'selectable_value_id' => $instance->selectable_value_id,
                'order' => $instance->order,
            ],
        ])->toArray();

        return $data;
    }
}
