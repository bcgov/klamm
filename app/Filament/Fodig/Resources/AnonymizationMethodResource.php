<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\AnonymizationMethodResource\Pages;
use App\Models\AnonymizationMethods;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AnonymizationMethodResource extends Resource
{
    protected static ?string $model = AnonymizationMethods::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Anonymizer';

    protected static ?string $navigationLabel = 'Methods';

    protected static ?int $navigationSort = 60;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Method Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('category')
                            ->maxLength(255)
                            ->placeholder('e.g. Deterministic, Masking'),
                        Forms\Components\Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Implementation Notes')
                    ->schema([
                        Forms\Components\Textarea::make('what_it_does')
                            ->label('What it does')
                            ->rows(4)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('how_it_works')
                            ->label('How it works')
                            ->rows(6)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('sql_block')
                            ->label('SQL block')
                            ->rows(10)
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Forms\Components\Section::make('Record Metadata')
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->created_at)?->toDayDateTimeString() ?? '—'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Updated')
                            ->content(fn(?AnonymizationMethods $record) => optional($record?->updated_at)?->toDayDateTimeString() ?? '—'),
                    ])
                    ->columns(2)
                    ->hiddenOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Columns')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn() => AnonymizationMethods::query()
                        ->whereNotNull('category')
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
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
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Method')
                                    ->weight('bold'),
                                TextEntry::make('category')
                                    ->placeholder('—'),
                            ])->columns(2),
                        TextEntry::make('description')
                            ->columnSpanFull()
                            ->placeholder('No description provided.'),
                    ]),
                InfolistSection::make('How It Works')
                    ->schema([
                        TextEntry::make('what_it_does')
                            ->label('What it does')
                            ->columnSpanFull()
                            ->placeholder('—'),
                        TextEntry::make('how_it_works')
                            ->label('How it works')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ])
                    ->collapsed()
                    ->collapsible(),
                InfolistSection::make('SQL Reference')
                    ->schema([
                        TextEntry::make('sql_block')
                            ->label('SQL block')
                            ->columnSpanFull()
                            ->placeholder('No SQL block documented.')
                            ->extraAttributes([
                                'class' => 'font-mono whitespace-pre-wrap text-sm'
                            ]),
                    ])
                    ->hidden(fn($record) => blank($record?->sql_block)),
                InfolistSection::make('Usage Metrics')
                    ->schema([
                        TextEntry::make('usage_count')
                            ->label('Columns using this method'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Created'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Updated'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnonymizationMethods::route('/'),
            'create' => Pages\CreateAnonymizationMethod::route('/create'),
            'view' => Pages\ViewAnonymizationMethod::route('/{record}'),
            'edit' => Pages\EditAnonymizationMethod::route('/{record}/edit'),
        ];
    }
}
