<?php

namespace App\Filament\Forms\Resources\RenderedFormResource\Pages;

use App\Filament\Forms\Resources\RenderedFormResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRenderedForms extends ListRecords
{
    protected static string $resource = RenderedFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('formBuilder')
                ->label('Form Builder')
                ->url(fn () => RenderedFormResource::getUrl('form-builder'))
                ->color('primary'),
        ];
    }
}
