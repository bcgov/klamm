<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAnonymizationJob extends ViewRecord
{
    protected static string $resource = AnonymizationJobResource::class;

    public bool $isSqlRegenerating = false;

    protected ?string $lastSqlScriptHash = null;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->syncSqlRegenerationState();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadSql')
                ->label('Download SQL')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->outlined()
                ->visible(fn() => filled($this->record->sql_script))
                ->tooltip('Exports the generated anonymization script for manual execution.')
                ->action(fn() => $this->downloadSqlScript()),
            // Queue SQL regeneration for background worker.
            Actions\Action::make('regenerateSql')
                ->label('Regenerate SQL')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->action(function () {
                    $cacheKey = GenerateAnonymizationJobSql::regenerationCacheKey((int) $this->record->getKey());
                    Cache::put($cacheKey, now()->toIso8601String(), now()->addHours(2));
                    $this->isSqlRegenerating = true;
                    $this->lastSqlScriptHash = md5((string) ($this->record->sql_script ?? ''));

                    GenerateAnonymizationJobSql::dispatch($this->record->getKey());
                    Notification::make()
                        ->success()
                        ->title('SQL regeneration queued')
                        ->body('The anonymization script will refresh automatically once complete.')
                        ->send();
                }),
            Actions\Action::make('viewSelection')
                ->label('View Selection')
                ->icon('heroicon-o-list-bullet')
                ->color('secondary')
                ->outlined()
                ->url(fn() => AnonymizationJobResource::getUrl('selection', ['record' => $this->record])),
            Actions\EditAction::make(),
        ];
    }

    public function refreshSqlPreview(): void
    {
        $cacheKey = GenerateAnonymizationJobSql::regenerationCacheKey((int) $this->record->getKey());
        $wasRegenerating = $this->isSqlRegenerating;

        $this->record->refresh();

        $this->isSqlRegenerating = Cache::has($cacheKey);
        $currentHash = md5((string) ($this->record->sql_script ?? ''));

        if (! $this->hasInfolist()) {
            $this->refreshFormData(['sql_script']);
            $this->form->fill([
                ...$this->form->getState(),
                'sql_script_preview' => $this->record->sql_script,
            ]);
        }

        if ($wasRegenerating && ! $this->isSqlRegenerating && $currentHash !== $this->lastSqlScriptHash) {
            Notification::make()
                ->success()
                ->title('SQL regeneration complete')
                ->body('The SQL preview has been refreshed.')
                ->send();
        }

        $this->lastSqlScriptHash = $currentHash;
    }

    protected function syncSqlRegenerationState(): void
    {
        $cacheKey = GenerateAnonymizationJobSql::regenerationCacheKey((int) $this->record->getKey());
        $this->isSqlRegenerating = Cache::has($cacheKey);
        $this->lastSqlScriptHash = md5((string) ($this->record->sql_script ?? ''));
    }

    protected function downloadSqlScript(): ?StreamedResponse
    {
        if (! filled($this->record->sql_script)) {
            Notification::make()
                ->warning()
                ->title('No script available')
                ->body('Generate the SQL before attempting to download the job output.')
                ->send();

            return null;
        }

        $timestamp = now()->format('Ymd_His');
        $name = Str::slug($this->record->name) ?: 'anonymization-job';
        $filename = $timestamp . '_' . $name . '.sql';
        $payload = $this->record->sql_script;

        return response()->streamDownload(function () use ($payload) {
            echo $payload;
        }, $filename, [
            'Content-Type' => 'text/sql; charset=UTF-8',
        ]);
    }
}
