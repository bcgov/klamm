<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormFieldResource\Pages;
use App\Filament\Resources\FormFieldResource\RelationManagers;
use App\Models\FormField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormFieldResource extends Resource
{
    protected static ?string $model = FormField::class;
    protected static ?string $navigationGroup = 'Forms';
    protected static ?string $navigationLabel = 'Fields';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('label'),
                Forms\Components\Textarea::make('help_text')
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_type_id')
                    ->relationship('dataType', 'name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('field_group_id')
                    ->relationship('fieldGroup', 'name'),
                Forms\Components\Textarea::make('validation')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('required')
                    ->required(),
                Forms\Components\Toggle::make('repeater')
                    ->required(),
                Forms\Components\TextInput::make('max_count'),
                Forms\Components\Textarea::make('conditional_logic')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('prepopulated')
                    ->required(),
                Forms\Components\Select::make('datasource_id')
                    ->relationship('datasource', 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('dataType.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fieldGroup.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('required')
                    ->boolean(),
                Tables\Columns\IconColumn::make('repeater')
                    ->boolean(),
                Tables\Columns\TextColumn::make('max_count')
                    ->searchable(),
                Tables\Columns\IconColumn::make('prepopulated')
                    ->boolean(),
                Tables\Columns\TextColumn::make('datasource.name')
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
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListFormFields::route('/'),
            'create' => Pages\CreateFormField::route('/create'),
            'view' => Pages\ViewFormField::route('/{record}'),
            'edit' => Pages\EditFormField::route('/{record}/edit'),
        ];
    }
}
