<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormDataSourceResource\Pages;
use App\Filament\Forms\Resources\FormDataSourceResource\RelationManagers;
use App\Models\FormDataSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Http\Middleware\CheckRole;

class FormDataSourceResource extends Resource
{
    protected static ?string $model = FormDataSource::class;

    protected static ?string $navigationIcon = 'icon-folder-git-2';

    protected static ?string $navigationGroup = 'Form Building';

    protected static ?string $navigationLabel = 'Databinding Sources';

    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin', 'form-developer');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
                Forms\Components\TextInput::make('type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('endpoint')
                    ->maxLength(255),
                Forms\Components\Textarea::make('params')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('body')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('headers')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('host')
                    ->maxLength(255),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('endpoint')
                    ->searchable(),
                Tables\Columns\TextColumn::make('host')
                    ->searchable(),
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
            'index' => Pages\ListFormDataSources::route('/'),
            'create' => Pages\CreateFormDataSource::route('/create'),
            'view' => Pages\ViewFormDataSource::route('/{record}'),
            'edit' => Pages\EditFormDataSource::route('/{record}/edit'),
        ];
    }
}
