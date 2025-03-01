<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelFieldResource\Pages;
use App\Filament\Fodig\Resources\SiebelFieldResource\RelationManagers;
use App\Models\SiebelField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelFieldResource extends Resource
{
    protected static ?string $model = SiebelField::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Select::make('business_component_id')
                    ->relationship('businessComponent', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('table_id')
                    ->relationship('table', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('table_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link_field')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('calculated_value')
                    ->maxLength(400)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessComponent.name')
                    ->label('Business Component')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table_column')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('multi_value_link')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('multi_value_link_field')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('join')
                    ->sortable(),
                Tables\Columns\TextColumn::make('join_column')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_value')
                    ->searchable()
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
                Tables\Filters\SelectFilter::make('business_component_id')
                    ->label('Business Component')
                    ->multiple()
                    ->searchable()
                    ->attribute('businessComponent.name')
                    ->relationship('businessComponent', 'name'),
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Table')
                    ->multiple()
                    ->searchable()
                    ->attribute('table.name')
                    ->relationship('table', 'name'),
                Tables\Filters\SelectFilter::make('table_column')
                    ->label('Table Column')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('multi_value_link')
                    ->label('Multi Value Link')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('multi_value_link_field')
                    ->label('Multi Value Link Field')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('join')
                    ->label('Join')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('join_column')
                    ->label('Join Column')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('calculated_value')
                    ->label('Calculated Value')
                    ->multiple()
                    ->searchable(),
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
            'index' => Pages\ListSiebelFields::route('/'),
            'create' => Pages\CreateSiebelField::route('/create'),
            'view' => Pages\ViewSiebelField::route('/{record}'),
            'edit' => Pages\EditSiebelField::route('/{record}/edit'),
        ];
    }
}
