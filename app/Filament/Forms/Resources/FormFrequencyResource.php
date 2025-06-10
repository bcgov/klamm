<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFrequencyResource\Pages;
use App\Filament\Forms\Resources\FormFrequencyResource\RelationManagers;
use App\Models\FormFrequency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormFrequencyResource extends Resource
{
    protected static ?string $model = FormFrequency::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Usage Frequency';
    protected static ?int $navigationSort = 2;


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
            'index' => Pages\ListFormFrequencies::route('/'),
            'create' => Pages\CreateFormFrequency::route('/create'),
            'edit' => Pages\EditFormFrequency::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Usage Frequency';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Usage Frequencies';
    }
}
