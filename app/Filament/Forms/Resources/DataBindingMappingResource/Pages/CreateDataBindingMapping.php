<?php

namespace App\Filament\Forms\Resources\DataBindingMappingResource\Pages;

use App\Filament\Forms\Resources\DataBindingMappingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDataBindingMapping extends CreateRecord
{
    protected static string $resource = DataBindingMappingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['data_path'] = DataBindingMappingResource::composeJsonPath(
            $data['data_source'] ?? '',
            $data['path_label']  ?? '',
        );

        return $data;
    }

}
