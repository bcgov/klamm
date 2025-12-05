<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymousUploadResource\Pages;
use App\Models\Anonymizer\AnonymousUpload;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AnonymousUploadResource extends Resource
{
    protected static ?string $model = AnonymousUpload::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Metadata Uploads';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'original_name';

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
                        Forms\Components\Textarea::make('status_detail')
                            ->label('Status Detail')
                            ->disabled()
                            ->columnSpanFull(),
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
                    ])
                    ->columns(4),
                Forms\Components\Section::make('Error Information')
                    ->schema([
                        Forms\Components\Textarea::make('error')
                            ->label('Error Message')
                            ->disabled()
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(?AnonymousUpload $record): bool => filled($record?->error)),
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
                TextColumn::make('created_at')
                    ->label('Queued At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
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
                InfolistSection::make('Upload Details')
                    ->schema([
                        InfolistGrid::make(2)
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
                InfolistSection::make('Import Configuration')
                    ->schema([
                        InfolistGrid::make(2)
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
                                TextEntry::make('status_detail')
                                    ->label('Status Detail')
                                    ->columnSpanFull(),
                            ]),
                    ]),
                InfolistSection::make('Processing Metrics')
                    ->schema([
                        InfolistGrid::make(4)
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
                        InfolistGrid::make(3)
                            ->schema([
                                TextEntry::make('progress_percent')
                                    ->label('Progress')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? "{$state}%" : '—'),
                                TextEntry::make('processed_bytes')
                                    ->label('Processed Bytes')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? self::formatFileSize($state) : '—'),
                                TextEntry::make('total_bytes')
                                    ->label('Total Bytes')
                                    ->formatStateUsing(fn(?int $state): string => $state !== null ? self::formatFileSize($state) : '—'),
                            ]),
                    ]),
                InfolistSection::make('Timestamps')
                    ->schema([
                        InfolistGrid::make(3)
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
                    ]),
                InfolistSection::make('Error Information')
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
            //
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

    private static function formatFileSize(?int $bytes): string
    {
        if ($bytes === null || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
