<?php

namespace App\Filament\Fodig\Resources;

use App\Helpers\StringHelper;
use App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;
use App\Models\Anonymizer\AnonymousUpload;
use App\Jobs\SyncAnonymousSiebelColumnsJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AnonymousUploadResource extends Resource
{
    protected static ?string $model = AnonymousUpload::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Metadata Uploads';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'original_name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Upload Details')
                    ->schema([
                        Forms\Components\TextInput::make('original_name')
                            ->label('File Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('file_name')
                            ->label('Stored File Name')
                            ->disabled(),
                        Forms\Components\TextInput::make('path')
                            ->label('Storage Path')
                            ->disabled(),
                        Forms\Components\TextInput::make('file_disk')
                            ->label('Disk')
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Import Configuration')
                    ->schema([
                        Forms\Components\Select::make('import_type')
                            ->label('Import Type')
                            ->options([
                                'full' => 'Full Import',
                                'partial' => 'Partial Import',
                            ])
                            ->disabled(),
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'queued' => 'Queued',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->disabled(),
                        Forms\Components\Toggle::make('override_anonymization_rules')
                            ->label('Override anonymization rules')
                            ->disabled(),
                        Forms\Components\Textarea::make('status_detail')
                            ->label('Status Detail')
                            ->disabled()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('run_phase')
                            ->label('Run Phase')
                            ->disabled(),
                        Forms\Components\TextInput::make('failed_phase')
                            ->label('Failed Phase')
                            ->disabled(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Processing Metrics')
                    ->schema([
                        Forms\Components\TextInput::make('inserted')
                            ->label('Inserted')
                            ->disabled(),
                        Forms\Components\TextInput::make('updated')
                            ->label('Updated')
                            ->disabled(),
                        Forms\Components\TextInput::make('deleted')
                            ->label('Deleted')
                            ->disabled(),
                        Forms\Components\TextInput::make('processed_rows')
                            ->label('Processed Rows')
                            ->disabled(),
                        Forms\Components\TextInput::make('warnings_count')
                            ->label('Warnings')
                            ->disabled(),
                    ])
                    ->columns(4),
                Forms\Components\Section::make('Error Information')
                    ->schema([
                        Forms\Components\Textarea::make('error')
                            ->label('Error Message')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('error_context')
                            ->label('Error Context')
                            ->disabled()
                            ->formatStateUsing(function ($state): string {
                                if (is_array($state)) {
                                    return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                }
                                return (string) ($state ?? '');
                            })
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(?AnonymousUpload $record): bool => filled($record?->error)),
                Forms\Components\Section::make('Warnings')
                    ->schema([
                        Forms\Components\Textarea::make('warnings')
                            ->label('Warnings')
                            ->disabled()
                            ->formatStateUsing(function ($state): string {
                                if (is_array($state)) {
                                    return (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                                }
                                return (string) ($state ?? '');
                            })
                            ->rows(6)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(?AnonymousUpload $record): bool => (int) ($record?->warnings_count ?? 0) > 0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('original_name')
                    ->label('File')
                    ->searchable()
                    ->sortable()
                    ->description(fn(AnonymousUpload $record): string => $record->created_at?->diffForHumans() ?? ''),
                TextColumn::make('import_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => Str::title($state))
                    ->color(fn(string $state): string => match ($state) {
                        'full' => 'danger',
                        'partial' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => Str::title($state))
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'failed' => 'danger',
                        'queued' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->formatStateUsing(fn(?int $state): string => $state !== null ? "{$state}%" : '—'),
                TextColumn::make('run_phase')
                    ->label('Phase')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('inserted')
                    ->label('Inserted')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('updated')
                    ->label('Updated')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('deleted')
                    ->label('Deleted')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('warnings_count')
                    ->label('Warnings')
                    ->badge()
                    ->color(fn(AnonymousUpload $record) => ($record->warnings_count ?? 0) > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn($state) => (int) $state > 0 ? (string) $state : '—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Queued At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('retention_until')
                    ->label('Retain Until')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_deleted_at')
                    ->label('File Deleted At')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'queued' => 'Queued',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('import_type')
                    ->options([
                        'full' => 'Full Import',
                        'partial' => 'Partial Import',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('resume_import')
                    ->label('Resume')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn(AnonymousUpload $record): bool => $record->status === 'failed')
                    ->action(function (AnonymousUpload $record): void {
                        SyncAnonymousSiebelColumnsJob::dispatch($record->id);
                    }),
                Tables\Actions\Action::make('restart_import')
                    ->label('Restart')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn(AnonymousUpload $record): bool => $record->status === 'failed')
                    ->action(function (AnonymousUpload $record): void {
                        SyncAnonymousSiebelColumnsJob::dispatch($record->id, true);
                    }),
                Tables\Actions\Action::make('delete_csv')
                    ->label('Delete CSV')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(function (AnonymousUpload $record): bool {
                        if ($record->file_deleted_at) {
                            return false;
                        }

                        $disk = $record->file_disk ?: config('filesystems.default', 'local');
                        return filled($record->path) && Storage::disk($disk)->exists($record->path);
                    })
                    ->action(function (AnonymousUpload $record): void {
                        $record->deleteStoredFile('manual');
                    }),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('10s');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Upload Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('original_name')
                                    ->label('Original File Name'),
                                TextEntry::make('file_name')
                                    ->label('Stored File Name'),
                                TextEntry::make('path')
                                    ->label('Storage Path'),
                                TextEntry::make('file_disk')
                                    ->label('Disk'),
                            ]),
                    ]),
                Section::make('Import Configuration')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('import_type')
                                    ->label('Import Type')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => Str::title($state))
                                    ->color(fn(string $state): string => match ($state) {
                                        'full' => 'danger',
                                        'partial' => 'info',
                                        default => 'gray',
                                    }),
                                TextEntry::make('status')
                                    ->badge()
                                    ->formatStateUsing(fn(string $state): string => Str::title($state))
                                    ->color(fn(string $state): string => match ($state) {
                                        'completed' => 'success',
                                        'processing' => 'info',
                                        'failed' => 'danger',
                                        'queued' => 'warning',
                                        default => 'gray',
                                    }),
                                TextEntry::make('create_change_tickets')
                                    ->label('Create Change Tickets')
                                    ->badge()
                                    ->formatStateUsing(fn($state): string => ($state === false) ? 'No' : 'Yes')
                                    ->color(fn($state): string => ($state === false) ? 'gray' : 'success'),
                                TextEntry::make('override_anonymization_rules')
                                    ->label('Override Anonymization Rules')
                                    ->badge()
                                    ->formatStateUsing(fn($state): string => ($state === true) ? 'Yes' : 'No')
                                    ->color(fn($state): string => ($state === true) ? 'warning' : 'gray'),
                                TextEntry::make('status_detail')
                                    ->label('Status Detail')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Section::make('Processing Metrics')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('inserted')
                                    ->label('Inserted')
                                    ->numeric(),
                                TextEntry::make('updated')
                                    ->label('Updated')
                                    ->numeric(),
                                TextEntry::make('deleted')
                                    ->label('Deleted')
                                    ->numeric(),
                                TextEntry::make('processed_rows')
                                    ->label('Processed Rows')
                                    ->numeric(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('progress_percent')
                                    ->label('Progress')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? "{$state}%" : '—'),
                                TextEntry::make('processed_bytes')
                                    ->label('Processed Bytes')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? StringHelper::formatFileSize($state) : '—'),
                                TextEntry::make('total_bytes')
                                    ->label('Total Bytes')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? StringHelper::formatFileSize($state) : '—'),
                            ]),
                    ]),
                Section::make('Timestamps')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Queued At')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime(),
                                TextEntry::make('progress_updated_at')
                                    ->label('Progress Updated At')
                                    ->dateTime(),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('retention_until')
                                    ->label('Retain Until')
                                    ->dateTime(),
                                TextEntry::make('file_deleted_at')
                                    ->label('File Deleted At')
                                    ->dateTime(),
                                TextEntry::make('file_deleted_reason')
                                    ->label('File Deleted Reason')
                                    ->formatStateUsing(fn(?string $state): string => $state ?: '—'),
                            ]),
                    ]),
                Section::make('Error Information')
                    ->schema([
                        TextEntry::make('error')
                            ->label('Error Message')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(?AnonymousUpload $record): bool => filled($record?->error)),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Fodig\RelationManagers\ActivityLogRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymousUploads::route('/'),
            'import' => Pages\ImportSiebelMetadata::route('/import'),
            'view' => Pages\ViewAnonymousUpload::route('/{record}'),
        ];
    }
}
