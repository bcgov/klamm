<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\BusinessFormResource\RelationManagers\BusinessFormGroupRelationManager;
use App\Filament\Forms\Resources\FormRepositoryResource\Pages;
use App\Filament\Forms\Resources\FormRepositoryResource\RelationManagers;
use App\Models\FormRepository;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormRepositoryResource extends Resource
{
    protected static ?string $model = FormRepository::class;

    protected static ?string $navigationGroup = 'Form Metadata';

    protected static ?string $navigationLabel = 'Repositories';
    protected static ?int $navigationSort = 8;

    protected static ?string $navigationIcon = 'icon-folder-kanban';

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
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormRepositories::route('/'),
            'create' => Pages\CreateFormRepository::route('/create'),
            'view' => Pages\ViewFormRepository::route('/{record}'),
            'edit' => Pages\EditFormRepository::route('/{record}/edit'),
        ];
    }
}
