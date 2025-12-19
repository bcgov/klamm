<?php

namespace App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;

use App\Filament\Fodig\Resources\AnonymousUploadResource;
use App\Models\Anonymizer\AnonymousUpload;
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
            Actions\Action::make('download_errors')
                ->label('Download Errors')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->visible(fn() => $this->canDownloadErrors())
                ->action(fn() => $this->downloadErrors()),
            Actions\Action::make('delete_csv')
                ->label('Delete CSV')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn() => $this->canDeleteCsv())
                ->action(fn() => $this->deleteCsv()),
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

    protected function canDownloadErrors(): bool
    {
        $disk = $this->record->file_disk ?: config('filesystems.default', 'local');
        $path = $this->record->path;

        if (! $path) {
            return false;
        }

        return Storage::disk($disk)->exists($path . '.errors.json');
    }

    protected function downloadErrors(): ?StreamedResponse
    {
        $disk = $this->record->file_disk ?: config('filesystems.default', 'local');
        $path = $this->record->path;
        $filenameBase = pathinfo((string) ($this->record->original_name ?: $this->record->file_name ?: 'upload.csv'), PATHINFO_FILENAME);
        $filename = $filenameBase . '.errors.json';

        if (! $path) {
            return null;
        }

        $errorPath = $path . '.errors.json';
        $storage = Storage::disk($disk);

        if (! $storage->exists($errorPath)) {
            return null;
        }

        return response()->streamDownload(function () use ($storage, $errorPath) {
            echo $storage->get($errorPath);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    protected function canDeleteCsv(): bool
    {
        /** @var AnonymousUpload $record */
        $record = $this->record;

        if ($record->file_deleted_at) {
            return false;
        }

        return $this->canDownload();
    }

    protected function deleteCsv(): void
    {
        /** @var AnonymousUpload $record */
        $record = $this->record;
        $record->deleteStoredFile('manual');
    }
}
