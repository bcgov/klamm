<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Concerns\HasMonacoSql;
use App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;
use App\Models\Anonymizer\AnonymizationPackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\Grid as InfolistGrid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class AnonymizationPackageResource extends Resource
{
    use HasMonacoSql;

    protected static ?string $model = AnonymizationPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Packages';

    protected static ?int $navigationSort = 65;

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
                Forms\Components\Section::make('Package Metadata')
                    ->description('Capture the SQL package name, target platform, and human-readable summary for downstream reviewers.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Display name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Example: ADPOC Toolkit'),
                        Forms\Components\TextInput::make('handle')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->maxLength(255)
                            ->placeholder('adpoc-toolkit')
                            ->helperText('Used internally to reference the package in automation scripts.'),
                        Forms\Components\TextInput::make('package_name')
                            ->label('Database package name')
                            ->maxLength(255)
                            ->placeholder('ADPOC')
                            ->helperText('Optional: Name of the database package (e.g. Oracle PL/SQL package).'),
                        Forms\Components\Select::make('database_platform')
                            ->label('Platform')
                            ->options(self::databasePlatformOptions())
                            ->default('oracle')
                            ->required()
                            ->helperText('Helps inform users which runtime this package was authored for.'),
                        Forms\Components\Textarea::make('summary')
                            ->label('Summary')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Describe what the package provides (deterministic hashing helpers, lookup tables, etc.).'),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('SQL Content')
                    ->description('Store the SQL blocks exactly as they should appear in exported scripts. They will be emitted in the order shown.')
                    ->schema([
                        self::sqlEditor(
                            field: 'install_sql',
                            label: 'Setup SQL (tables, grants, data)',
                            height: '250px',
                            helperText: 'Optional DDL/DML for supporting tables, lookup data, or helper views.'
                        ),
                        self::sqlEditor(
                            field: 'package_spec_sql',
                            label: 'Package spec / header',
                            height: '300px',
                            helperText: 'The CREATE OR REPLACE PACKAGE statement (include terminators like `/` when needed).'
                        ),
                        self::sqlEditor(
                            field: 'package_body_sql',
                            label: 'Package body / implementation',
                            height: '350px',
                            helperText: 'The CREATE OR REPLACE PACKAGE BODY statement (include terminators like `/` when needed).'
                        ),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Record Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn(?AnonymizationPackage $record) => optional($record?->created_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated')
                            ->content(fn(?AnonymizationPackage $record) => optional($record?->updated_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Package')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('version')
                    ->label('Ver')
                    ->badge()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_current')
                    ->label('Current')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('handle')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('package_name')
                    ->label('DB package')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('database_platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => self::databasePlatformOptions()[$state] ?? Str::headline($state))
                    ->color('gray'),
                TextColumn::make('methods_count')
                    ->label('Methods')
                    ->counts('methods')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('database_platform')
                    ->label('Platform')
                    ->options(self::databasePlatformOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('new_version')
                    ->label('New version')
                    ->icon('heroicon-o-document-duplicate')
                    ->requiresConfirmation()
                    ->modalHeading('Create a new package version?')
                    ->modalDescription('This will duplicate the package settings into a new record. It will not be attached to any methods automatically.')
                    ->modalSubmitActionLabel('Create version')
                    ->action(function (AnonymizationPackage $record) {
                        $new = $record->createNewVersion();

                        return redirect(self::getUrl('edit', ['record' => $new]));
                    }),
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->requiresConfirmation(fn(AnonymizationPackage $record) => $record->isInUse())
                    ->modalHeading('Edit package currently in use?')
                    ->modalDescription(fn(AnonymizationPackage $record) => self::packageUsageWarning($record))
                    ->modalSubmitActionLabel('Continue to edit')
                    ->form(fn(AnonymizationPackage $record) => $record->isInUse()
                        ? [
                            Checkbox::make('acknowledge')
                                ->label('I understand that editing this package can affect generated scripts for existing jobs/columns.')
                                ->accepted()
                                ->required(),
                        ]
                        : [])
                    ->action(fn(AnonymizationPackage $record) => redirect(self::getUrl('edit', ['record' => $record]))),
                Tables\Actions\Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn(AnonymizationPackage $record) => $record->isInUse() ? 'Delete package currently in use?' : 'Delete package?')
                    ->modalDescription(fn(AnonymizationPackage $record) => $record->isInUse()
                        ? (self::packageUsageWarning($record) . ' Deleting will remove this package from future exports, but may impact the ability to re-generate scripts for already-scoped jobs.')
                        : 'This will soft-delete the anonymization package.')
                    ->modalSubmitActionLabel('Delete package')
                    ->form(fn(AnonymizationPackage $record) => $record->isInUse()
                        ? [
                            Checkbox::make('acknowledge')
                                ->label('I understand and want to delete this package anyway.')
                                ->accepted()
                                ->required(),
                        ]
                        : [])
                    ->action(fn(AnonymizationPackage $record) => $record->delete()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected packages?')
                        ->modalDescription(function ($records) {
                            $total = is_iterable($records) ? count($records) : 0;

                            $inUseCount = 0;
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationPackage && $record->isInUse()) {
                                    $inUseCount++;
                                }
                            }

                            if ($inUseCount > 0) {
                                return "{$inUseCount} of {$total} selected packages are currently required by methods used in jobs/columns. This operation requires explicit acknowledgement.";
                            }

                            return 'This will soft-delete the selected anonymization packages.';
                        })
                        ->form(function ($records) {
                            $anyInUse = false;
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationPackage && $record->isInUse()) {
                                    $anyInUse = true;
                                    break;
                                }
                            }

                            return $anyInUse
                                ? [
                                    Checkbox::make('acknowledge')
                                        ->label('I understand and want to delete packages that are currently in use.')
                                        ->accepted()
                                        ->required(),
                                ]
                                : [];
                        })
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if ($record instanceof AnonymizationPackage) {
                                    $record->delete();
                                }
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Summary')
                    ->schema([
                        InfolistGrid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextEntry::make('name')
                                ->label('Package')
                                ->weight('bold'),
                            TextEntry::make('database_platform')
                                ->label('Platform')
                                ->formatStateUsing(fn(string $state) => self::databasePlatformOptions()[$state] ?? Str::headline($state)),
                            TextEntry::make('package_name')
                                ->label('DB package')
                                ->placeholder('—'),
                            TextEntry::make('handle')
                                ->label('Handle'),
                        ]),
                        TextEntry::make('summary')
                            ->columnSpanFull()
                            ->placeholder('No summary provided.'),
                    ]),
                InfolistSection::make('SQL Blocks')
                    ->schema([
                        self::sqlViewer(field: 'install_sql', label: 'Setup SQL', height: '250px'),
                        self::sqlViewer(field: 'package_spec_sql', label: 'Package spec', height: '300px'),
                        self::sqlViewer(field: 'package_body_sql', label: 'Package body', height: '350px'),
                    ])
                    ->columns(1),
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
            'index' => Pages\ListAnonymizationPackages::route('/'),
            'create' => Pages\CreateAnonymizationPackage::route('/create'),
            'view' => Pages\ViewAnonymizationPackage::route('/{record}'),
            'edit' => Pages\EditAnonymizationPackage::route('/{record}/edit'),
        ];
    }

    public static function databasePlatformOptions(): array
    {
        return [
            'oracle' => 'Oracle / Siebel',
            'postgres' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'sqlserver' => 'SQL Server',
            'generic' => 'Generic SQL',
        ];
    }

    protected static function packageUsageWarning(AnonymizationPackage $record): string
    {
        if (! $record->isInUse()) {
            return 'This package is not currently required by any methods used in jobs/columns.';
        }

        // Keep this intentionally high-level to avoid expensive, detailed counting queries.
        return 'This package is attached to methods that are already used by jobs/columns. Changes here can impact generated anonymization SQL. Consider using “New version” to preserve existing behavior.';
    }
}
