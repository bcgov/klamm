<?php

namespace App\Filament\Fodig\Resources\AnonymizationJobResource\Pages;

use App\Filament\Fodig\Resources\AnonymizationJobResource;
use App\Jobs\GenerateAnonymizationJobSql;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewAnonymizationJob extends ViewRecord
{
    protected static string $resource = AnonymizationJobResource::class;

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
            Actions\Action::make('regenerateSql')
                ->label('Regenerate SQL')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->outlined()
                ->requiresConfirmation()
                ->action(function () {
                    GenerateAnonymizationJobSql::dispatch($this->record->getKey());

                    Notification::make()
                        ->success()
                        ->title('SQL regeneration queued')
                        ->body('The anonymization script will refresh shortly.')
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
