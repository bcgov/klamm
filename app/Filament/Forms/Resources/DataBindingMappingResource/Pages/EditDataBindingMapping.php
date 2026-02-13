<?php

namespace App\Filament\Forms\Resources\DataBindingMappingResource\Pages;

use App\Filament\Forms\Resources\DataBindingMappingResource;
use Filament\Resources\Pages\EditRecord;

class EditDataBindingMapping extends EditRecord
{
    protected static string $resource = DataBindingMappingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['data_path'] = DataBindingMappingResource::composeJsonPath(
            $data['data_source'] ?? '',
            $data['path_label']  ?? '',
        );

        return $data;
    }

}
