<?php

namespace App\Filament\Forms\Resources\FormFrequencyResource\Pages;

use App\Filament\Forms\Resources\FormFrequencyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormFrequency extends EditRecord
{
    protected static string $resource = FormFrequencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}