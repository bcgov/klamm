<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormResource;
use App\Filament\Forms\Resources\FormVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditFormVersion extends EditRecord
{
    protected static string $resource = FormVersionResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record->id]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            FormVersionResource::getUrl('index') => 'Form Versions',
            FormResource::getUrl('view', ['record' => $this->record->form->id]) => "{$this->record->form->form_id}",
            FormVersionResource::getUrl('view', ['record' => $this->record]) => "Version {$this->record->version_number}",
            '#' => 'Edit Form Version',
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->color('danger')
                ->visible(fn() => $this->record->status !== 'archived' && (Gate::allows('admin') || Gate::allows('form-developer')))
                ->action(function () {
                    $this->record->update(['status' => 'archived']);
                    $this->redirect($this->getRedirectUrl());
                })
                ->requiresConfirmation()
                ->modalHeading('Archive Form Version')
                ->modalDescription('Are you sure you want to archive this form version? This will change its status to archived.')
                ->modalSubmitActionLabel('Archive'),
            Actions\Action::make('build')
                ->label('Build')
                ->icon('heroicon-o-wrench-screwdriver')
                ->url(fn() => FormVersionResource::getUrl('build', ['record' => $this->record]))
                ->color('primary')
                ->outlined()
                ->visible(fn() => Gate::allows('form-developer')),
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
