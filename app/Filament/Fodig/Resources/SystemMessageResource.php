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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('error_code')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('error_message')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('icm_error_solution')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('explanation')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('fix')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('error_entity_id')
                    ->numeric(),
                Forms\Components\TextInput::make('error_data_group_id')
                    ->numeric(),
                Forms\Components\TextInput::make('error_integration_state_id')
                    ->numeric(),
                Forms\Components\TextInput::make('error_actor_id')
                    ->numeric(),
                Forms\Components\TextInput::make('error_source_id')
                    ->numeric(),
                Forms\Components\Toggle::make('service_desk'),
                Forms\Components\Toggle::make('limited_data'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('error_entity_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_data_group_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_integration_state_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_actor_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error_source_id')
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
