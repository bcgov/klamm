<?php

namespace App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;

use App\Filament\Fodig\Resources\AnonymousUploadResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAnonymousUpload extends ViewRecord
{
    protected static string $resource = AnonymousUploadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn() => $this->canDownload())
                ->action(fn() => $this->downloadCsv()),
            Actions\Action::make('import')
                ->label('New Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(fn() => AnonymousUploadResource::getUrl('import')),
            Actions\DeleteAction::make(),
        ];
    }

    protected function canDownload(): bool
    {
        $disk = $this->record->file_disk ?: config('filesystems.default', 'local');
        $path = $this->record->path;

        return $path && Storage::disk($disk)->exists($path);
    }

    protected function downloadCsv(): ?StreamedResponse
    {
        $disk = $this->record->file_disk ?: config('filesystems.default', 'local');
        $path = $this->record->path;
        $filename = $this->record->original_name ?: $this->record->file_name ?: 'download.csv';

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $storage = Storage::disk($disk);

        return response()->streamDownload(function () use ($storage, $path) {
            echo $storage->get($path);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
