<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
                ->visible(fn() => ((int) ($this->record->sql_script_length ?? 0)) > 0)
                ->tooltip('Exports the generated anonymization script for manual execution.')
                ->url(fn() => route('download.anonymization-job-sql', ['job' => $this->record->getKey()])),
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
                    $this->lastSqlScriptHash = $this->getSqlScriptHashFromDb();

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

        // Refresh the record WITHOUT loading sql_script (which can be 50+ MB).
        $fresh = DB::table('anonymization_jobs')
            ->where('id', $this->record->getKey())
            ->selectRaw('length(sql_script) as sql_script_length')
            ->first();

        if ($fresh) {
            $this->record->sql_script_length = $fresh->sql_script_length;
        }

        $this->isSqlRegenerating = Cache::has($cacheKey);
        $currentHash = $this->getSqlScriptHashFromDb();

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
        $this->lastSqlScriptHash = $this->getSqlScriptHashFromDb();
    }

    /**
     * Get an MD5 hash of the sql_script directly from the database,
     * without loading the potentially huge column into PHP memory.
     */
    protected function getSqlScriptHashFromDb(): string
    {
        return (string) DB::table('anonymization_jobs')
            ->where('id', $this->record->getKey())
            ->selectRaw("COALESCE(md5(sql_script), '') as hash")
            ->value('hash');
    }
}
