<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function canEdit($record): bool
    {
        return Gate::allows('form-developer') && in_array($record->status, ['draft', 'testing']);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
