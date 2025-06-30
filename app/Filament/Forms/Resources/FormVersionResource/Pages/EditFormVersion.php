<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('Preview Draft Template')
                ->label('Preview Draft')
                ->icon('heroicon-o-rocket-launch')
                ->extraAttributes([
                    'style' => 'background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none;'
                ])
                ->action(function ($livewire) {
                    $formVersionId = $this->record->id;
                    $previewBaseUrl = env('FORM_PREVIEW_URL', '');
                    $previewUrl = rtrim($previewBaseUrl, '/') . '/preview/' . $formVersionId . '?draft=true';
                    $livewire->js("window.open('$previewUrl', '_blank')");
                }),
        ];
    }
}
