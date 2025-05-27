<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\BoundarySystemResource\Pages;
use App\Models\BoundarySystem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BoundarySystemResource extends Resource
{
    protected static ?string $model = BoundarySystem::class;

    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'Data Gateway';

    protected static ?string $navigationLabel = 'Systems';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Select::make('contact_id')
                    ->relationship('contact', 'name')
                    ->searchable()
                    ->columnSpanFull()
                    ->preload(),
                Forms\Components\Radio::make('is_external')
                    ->label('System Type')
                    ->options([
                        false => 'Internal',
                        true => 'External',
                    ])
                    ->default(false)
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('is_external')
                    ->label('System Type')
                    ->formatStateUsing(fn(bool $state) => $state ? 'External' : 'Internal')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'index' => Pages\ListBoundarySystems::route('/'),
            'create' => Pages\CreateBoundarySystem::route('/create'),
            'view' => Pages\ViewBoundarySystem::route('/{record}'),
            'edit' => Pages\EditBoundarySystem::route('/{record}/edit'),
        ];
    }
}
