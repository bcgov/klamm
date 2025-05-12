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

        $data['select_option_instances'] = $this->record->selectOptionInstances->map(fn($instance) => [
            'type' => 'select_option_instance',
            'data' => [
                'select_option_id' => $instance->select_option_id,
                'order' => $instance->order,
            ],
        ])->toArray();

        return $data;
    }
}
