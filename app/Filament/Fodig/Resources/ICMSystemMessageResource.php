<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\ICMSystemMessageResource\Pages;
use App\Filament\Fodig\Resources\ICMSystemMessageResource\RelationManagers;
use App\Models\ICMSystemMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ICMSystemMessageResource extends Resource
{
    protected static ?string $model = ICMSystemMessage::class;


    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-oval-left';

    protected static ?string $navigationGroup = 'Successor System';

    protected static ?string $label = "ICM System Message";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextArea::make('view')->nullable(),
                Forms\Components\TextArea::make('business_rule')->nullable(),
                Forms\Components\TextInput::make('rule_number')->numeric()->nullable(),
                Forms\Components\Textarea::make('message_copy')->nullable(),
                Forms\Components\Textarea::make('fix')->nullable(),
                Forms\Components\Textarea::make('explanation')->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('rule_number'),
                Tables\Columns\TextColumn::make('message_copy')->limit(50),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])->paginated([
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
            'index' => Pages\ListICMSystemMessages::route('/'),
            'create' => Pages\CreateICMSystemMessage::route('/create'),
            'edit' => Pages\EditICMSystemMessage::route('/{record}/edit'),
        ];
    }
}
