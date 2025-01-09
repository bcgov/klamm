<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\SelectOptionsResource\Pages;
use App\Filament\Forms\Resources\SelectOptionsResource\RelationManagers;
use App\Models\SelectOptions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SelectOptionsResource extends Resource
{
    protected static ?string $model = SelectOptions::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Form Building';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->unique()
                    ->required(),
                Forms\Components\TextInput::make('label'),
                Forms\Components\TextInput::make('value'),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('formFields')
                    ->relationship('formFields', 'label', function ($query) {
                        $query->whereHas('dataType', function ($query) {
                            $query->whereIn('name', ['radio', 'dropdown']);
                        });
                    })
                    ->multiple()
                    ->preload()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('value')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('formFields.label')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListSelectOptions::route('/'),
            'create' => Pages\CreateSelectOptions::route('/create'),
            'view' => Pages\ViewSelectOptions::route('/{record}'),
            'edit' => Pages\EditSelectOptions::route('/{record}/edit'),
        ];
    }
}
