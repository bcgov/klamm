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

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'Successor System';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Radio::make('message_type_id')
                    ->label('Message Type')
                    ->options(
                        \App\Models\MessageType::all()->pluck('name', 'id')->toArray()
                    )
                    ->required(),
                Forms\Components\Select::make('data_group_id')
                    ->relationship('dataGroup', 'name')
                    ->nullable()
                    ->preload(),
                Forms\Components\TextInput::make('icm_error_code')
                    ->maxLength(255)
                    ->label('ICM Errror Code'),
                Forms\Components\Textarea::make('message_copy'),
                Forms\Components\Textarea::make('view'),
                Forms\Components\Textarea::make('fix'),
                Forms\Components\Textarea::make('explanation'),
                Forms\Components\Textarea::make('business_rule'),
                Forms\Components\TextInput::make('rule_number')
                    ->numeric(),
                Forms\Components\Select::make('ministry_ids')
                    ->relationship('ministries', 'name')
                    ->multiple()
                    ->preload(),
                Forms\Components\Textarea::make('reference')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('messageType.name'),
                Tables\Columns\TextColumn::make('dataGroup.name'),
                Tables\Columns\TextColumn::make('icm_error_code')
                    ->label('ICM Error Code'),
                Tables\Columns\TextColumn::make('rule_number'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('message_type_id')
                    ->label('Message Type')
                    ->options(
                        \App\Models\MessageType::all()->pluck('name', 'id')->toArray()
                    ),
                Tables\Filters\SelectFilter::make('data_group_id')
                    ->label('Data Group')
                    ->options(
                        \App\Models\DataGroup::all()->pluck('name', 'id')->toArray()
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemMessages::route('/'),
            'create' => Pages\CreateSystemMessage::route('/create'),
            'edit' => Pages\EditSystemMessage::route('/{record}/edit'),
        ];
    }
}
