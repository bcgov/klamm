<?php

namespace App\Filament\Forms\Resources\FormSchemaImporterResource\Pages;

use App\Filament\Forms\Resources\FormSchemaImporterResource;
use App\Models\FormSchemaImportSession;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

class ListSchemaImport extends ListRecords
{
    protected static string $resource = FormSchemaImporterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('new_import')
                ->label('New Schema Import')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(route('filament.forms.resources.schema-import.import')),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FormSchemaImportSession::query()->forCurrentUser()->latest())
            ->columns([
                TextColumn::make('session_name')
                    ->label('Import Session')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'draft',
                        'blue' => 'in_progress',
                        'green' => 'completed',
                        'red' => 'failed',
                        'orange' => 'cancelled',
                    ]),

                TextColumn::make('target_form_id')
                    ->label('Target Form')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                TextColumn::make('targetMinistry.name')
                    ->label('Ministry')
                    ->searchable()
                    ->sortable()
                    ->limit(25),

                TextColumn::make('total_fields')
                    ->label('Total Fields')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('mapped_fields')
                    ->label('Mapped')
                    ->sortable()
                    ->alignCenter()
                    ->formatStateUsing(function ($state, $record) {
                        if ($record->total_fields > 0) {
                            $percentage = round(($state / $record->total_fields) * 100);
                            return "{$state}/{$record->total_fields} ({$percentage}%)";
                        }
                        return $state;
                    }),

                TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->since()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Action::make('resume')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn(FormSchemaImportSession $record): bool => $record->canBeResumed())
                    ->url(
                        fn(FormSchemaImportSession $record): string =>
                        route('filament.forms.resources.schema-import.import_session', ['session' => $record->session_token])
                    ),

                Action::make('view_result')
                    ->label('View Result')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn(FormSchemaImportSession $record): bool => $record->status === 'completed' && $record->result_form_version_id)
                    ->url(
                        fn(FormSchemaImportSession $record): string =>
                        route('filament.forms.resources.form-versions.view', ['record' => $record->result_form_version_id])
                    )
                    ->openUrlInNewTab(),

                Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (FormSchemaImportSession $record) {
                        $cloned = $record->replicate([
                            'session_token',
                            'result_form_id',
                            'result_form_version_id',
                            'import_result',
                            'completed_at',
                            'error_message'
                        ]);

                        $cloned->session_name = $record->session_name . ' (Copy)';
                        $cloned->status = 'draft';
                        $cloned->session_token = FormSchemaImportSession::generateSessionToken();
                        $cloned->save();

                        Notification::make()
                            ->title('Session Cloned')
                            ->body('A copy of the import session has been created.')
                            ->success()
                            ->send();

                        return redirect(route('filament.forms.resources.schema-import.import_session', ['session' => $cloned->session_token]));
                    }),

                DeleteAction::make()
                    ->visible(fn(FormSchemaImportSession $record): bool => $record->canBeDeleted()),
            ])
            ->bulkActions([
                BulkAction::make('bulk_delete')
                    ->label('Delete Selected')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Selected Sessions')
                    ->modalDescription('Are you sure you want to delete the selected import sessions? This action cannot be undone.')
                    ->action(function (Collection $records) {
                        $count = $records->count();
                        $records->each(function (FormSchemaImportSession $record) {
                            if ($record->canBeDeleted()) {
                                $record->delete();
                            }
                        });

                        Notification::make()
                            ->title('Sessions Deleted')
                            ->body("{$count} import session(s) have been deleted.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('last_activity_at', 'desc')
            ->emptyStateHeading('No Import Sessions')
            ->emptyStateDescription('Get started by creating your first schema import session.')
            ->emptyStateIcon('heroicon-o-document-arrow-up')
            ->emptyStateActions([
                Action::make('new_import')
                    ->label('Start New Import')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(route('filament.forms.resources.schema-import.import')),
            ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            route('filament.forms.pages.forms-dashboard') => 'Forms Dashboard',
            '#' => 'Schema Import Sessions',
        ];
    }
}
