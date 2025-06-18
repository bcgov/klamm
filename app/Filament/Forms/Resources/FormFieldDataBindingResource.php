<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFieldDataBindingResource\Pages;
use App\Models\FormFieldDataBinding;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FormFieldDataBindingResource extends Resource
{
    protected static ?string $model = FormFieldDataBinding::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Form Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_sources_id')
                    ->relationship('dataSource', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('data_binding_path')
                    ->maxLength(255),
                Forms\Components\TextInput::make('data_binding_type')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dataSource.name')
                    ->label('Data Source')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_binding_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('data_binding_type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => Pages\ListFormFieldDataBindings::route('/'),
            'create' => Pages\CreateFormFieldDataBinding::route('/create'),
            'edit' => Pages\EditFormFieldDataBinding::route('/{record}/edit'),
        ];
    }
}
