<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\SelectOptionFormElementResource\Pages;
use App\Models\SelectOptionFormElement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SelectOptionFormElementResource extends Resource
{
    protected static ?string $model = SelectOptionFormElement::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationGroup = 'Form Builder';

    protected static ?string $navigationLabel = 'Select Options';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('select_input_form_elements_id')
                    ->relationship('selectInputFormElement', 'id')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('label')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('value')
                    ->maxLength(255),
                Forms\Components\TextInput::make('order')
                    ->numeric()
                    ->default(0),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('selectInputFormElement.id')
                    ->label('Select Element ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->searchable(),
                Tables\Columns\TextColumn::make('order')
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->limit(50),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
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
            'index' => Pages\ListSelectOptionFormElements::route('/'),
            'create' => Pages\CreateSelectOptionFormElement::route('/create'),
            'edit' => Pages\EditSelectOptionFormElement::route('/{record}/edit'),
        ];
    }
}
