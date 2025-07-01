<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormSoftwareSourceResource\Pages;
use App\Filament\Forms\Resources\FormSoftwareSourceResource\RelationManagers;
use App\Models\FormMetadata\FormSoftwareSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormSoftwareSourceResource extends Resource
{
    protected static ?string $model = FormSoftwareSource::class;

    protected static ?string $navigationIcon = 'icon-server';

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Software Sources';
    protected static ?int $navigationSort = 5;


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
            'index' => Pages\ListFormSoftwareSources::route('/'),
            'create' => Pages\CreateFormSoftwareSource::route('/create'),
            'edit' => Pages\EditFormSoftwareSource::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Software Source';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Software Sources';
    }
}
