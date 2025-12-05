<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationPackageResource\Pages;
use App\Models\AnonymizationPackage;
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

class AnonymizationPackageResource extends Resource
{
    protected static ?string $model = AnonymizationPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Packages';

    protected static ?int $navigationSort = 65;

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
                        Forms\Components\Textarea::make('install_sql')
                            ->label('Setup SQL (tables, grants, data)')
                            ->rows(10)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('Optional DDL/DML used to create supporting tables, lookup data, or helper views.'),
                        Forms\Components\Textarea::make('package_spec_sql')
                            ->label('Package spec / header')
                            ->rows(12)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('The CREATE OR REPLACE PACKAGE statement. Include terminators (/) as needed.'),
                        Forms\Components\Textarea::make('package_body_sql')
                            ->label('Package body / implementation')
                            ->rows(16)
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->helperText('The CREATE OR REPLACE PACKAGE BODY statement. Include terminators (/) as needed.'),
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
                Tables\Filters\SelectFilter::make('database_platform')
                    ->label('Platform')
                    ->options(self::databasePlatformOptions()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
                        TextEntry::make('install_sql')
                            ->label('Setup SQL')
                            ->placeholder('—')
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap text-sm bg-slate-950/5 rounded-lg p-4']),
                        TextEntry::make('package_spec_sql')
                            ->label('Package spec')
                            ->placeholder('—')
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap text-sm bg-slate-950/5 rounded-lg p-4']),
                        TextEntry::make('package_body_sql')
                            ->label('Package body')
                            ->placeholder('—')
                            ->extraAttributes(['class' => 'font-mono whitespace-pre-wrap text-sm bg-slate-950/5 rounded-lg p-4']),
                    ])
                    ->columns(1),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
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
}
