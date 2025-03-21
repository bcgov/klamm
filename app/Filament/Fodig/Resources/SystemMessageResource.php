<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SystemMessageResource\Pages;
use App\Filament\Fodig\Resources\SystemMessageResource\RelationManagers;
use App\Models\SystemMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SystemMessageResource extends Resource
{
    protected static ?string $model = SystemMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationGroup = 'Error Lookup Tool';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('error_code')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('error_message')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('icm_error_solution')
                    ->maxLength(255),
                Forms\Components\TextInput::make('explanation')
                    ->maxLength(255),
                Forms\Components\TextInput::make('fix')
                    ->maxLength(255),
                Forms\Components\Select::make('error_entity_id')
                    ->relationship('errorEntity', 'name'),
                Forms\Components\Select::make('error_data_group_id')
                    ->relationship('errorDataGroup', 'name'),
                Forms\Components\Select::make('error_integration_state_id')
                    ->relationship('errorIntegrationState', 'name'),
                Forms\Components\Select::make('error_actor_id')
                    ->relationship('errorActor', 'name'),
                Forms\Components\Select::make('error_source_id')
                    ->relationship('errorSource', 'name'),
                Forms\Components\Toggle::make('service_desk'),
                Forms\Components\Toggle::make('limited_data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('error_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('error_message')
                    ->searchable(),
                Tables\Columns\TextColumn::make('icm_error_solution')
                    ->searchable(),
                Tables\Columns\TextColumn::make('explanation')
                    ->searchable(),
                Tables\Columns\TextColumn::make('fix')
                    ->searchable(),
                Tables\Columns\TextColumn::make('errorEntity.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('errorDataGroup.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('errorIntegrationState.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('errorActor.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('errorSource.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\IconColumn::make('service_desk')
                    ->boolean(),
                Tables\Columns\IconColumn::make('limited_data')
                    ->boolean(),
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
            'index' => Pages\ListSystemMessages::route('/'),
            'create' => Pages\CreateSystemMessage::route('/create'),
            'view' => Pages\ViewSystemMessage::route('/{record}'),
            'edit' => Pages\EditSystemMessage::route('/{record}/edit'),
        ];
    }
}
