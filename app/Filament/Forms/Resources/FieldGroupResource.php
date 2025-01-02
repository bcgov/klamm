<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FieldGroupResource\Pages;
use App\Models\FieldGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;

class FieldGroupResource extends Resource
{
    protected static ?string $model = FieldGroup::class;

    protected static ?string $navigationGroup = 'Form Building';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->unique()
                    ->required(),
                TextInput::make('label')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Textarea::make('internal_description')
                    ->columnSpanFull(),
                Select::make('form_field_ids')
                    ->label('Form Fields')
                    ->multiple()
                    ->relationship('formFields', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('repeater'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('label')->sortable()->searchable(),
                Tables\Columns\IconColumn::make('repeater')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListFieldGroups::route('/'),
            'create' => Pages\CreateFieldGroup::route('/create'),
            'view' => Pages\ViewFieldGroup::route('/{record}'),
            'edit' => Pages\EditFieldGroup::route('/{record}/edit'),
        ];
    }
}
