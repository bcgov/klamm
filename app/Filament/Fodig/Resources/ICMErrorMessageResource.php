<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\ICMErrorMessageResource\Pages;
use App\Filament\Fodig\Resources\ICMErrorMessageResource\RelationManagers;
use App\Models\ICMErrorMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ICMErrorMessageResource extends Resource
{
    protected static ?string $model = ICMErrorMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-circle';

    protected static ?string $navigationGroup = 'Successor System';

    protected static ?string $label = "ICM Error Message";

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('icm_error_code')->nullable()->label('ICM error code'),
                Forms\Components\Textarea::make('business_rule')->nullable(),
                Forms\Components\TextInput::make('rule_number')->numeric()->nullable(),
                Forms\Components\Textarea::make('message_copy')->nullable(),
                Forms\Components\Textarea::make('fix')->nullable(),
                Forms\Components\Textarea::make('explanation')->nullable(),
                Forms\Components\Textarea::make('reference')->nullable(),
                Forms\Components\Select::make('ministry_ids')
                    ->label('Ministries')
                    ->relationship('ministries', 'name')
                    ->multiple()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icm_error_code')->label('ICM Error Code'),
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
            'index' => Pages\ListICMErrorMessages::route('/'),
            'create' => Pages\CreateICMErrorMessage::route('/create'),
            'edit' => Pages\EditICMErrorMessage::route('/{record}/edit'),
        ];
    }
}
