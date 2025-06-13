<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\UserTypeResource\Pages;
use App\Filament\Forms\Resources\UserTypeResource\RelationManagers;
use App\Models\UserType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserTypeResource extends Resource
{
    protected static ?string $model = UserType::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Audience';
    protected static ?int $navigationSort = 3;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListUserTypes::route('/'),
            'create' => Pages\CreateUserType::route('/create'),
            'edit' => Pages\EditUserType::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Audience';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Audiences';
    }
}
