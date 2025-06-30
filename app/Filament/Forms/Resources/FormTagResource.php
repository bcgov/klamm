<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormTagResource\Pages;
use App\Filament\Forms\Resources\FormTagResource\RelationManagers;
use App\Models\FormMetadata\FormTag;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormTagResource extends Resource
{
    protected static ?string $model = FormTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Form Metadata';
    protected static ?string $navigationLabel = 'Tags';
    protected static ?int $navigationSort = 7;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull(),
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
            RelationManagers\FormsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormTags::route('/'),
            'create' => Pages\CreateFormTag::route('/create'),
            'edit' => Pages\EditFormTag::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Tag';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Tags';
    }
}
