<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelBusinessServiceResource\Pages;
use App\Filament\Fodig\Resources\SiebelBusinessServiceResource\RelationManagers;
use App\Models\SiebelBusinessService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelBusinessServiceResource extends Resource
{
    protected static ?string $model = SiebelBusinessService::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('repository_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('cache'),
                Forms\Components\TextInput::make('display_name')
                    ->maxLength(200),
                Forms\Components\TextInput::make('display_name_string_reference')
                    ->maxLength(200),
                Forms\Components\TextInput::make('display_name_string_override')
                    ->maxLength(200),
                Forms\Components\Toggle::make('external_use'),
                Forms\Components\Toggle::make('hidden'),
                Forms\Components\Toggle::make('server_enabled')
                    ->required(),
                Forms\Components\TextInput::make('state_management_type')
                    ->maxLength(25),
                Forms\Components\Toggle::make('web_service_enabled'),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('cache')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name_string_reference')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name_string_override')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('external_use')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('hidden')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('server_enabled')
                    ->sortable(),
                Tables\Columns\TextColumn::make('state_management_type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('web_service_enabled')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comments')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('object_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('object_language_locked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('class.name')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    //
                ]),
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
            'index' => Pages\ListSiebelBusinessServices::route('/'),
            'create' => Pages\CreateSiebelBusinessService::route('/create'),
            'view' => Pages\ViewSiebelBusinessService::route('/{record}'),
            'edit' => Pages\EditSiebelBusinessService::route('/{record}/edit'),
        ];
    }
}
