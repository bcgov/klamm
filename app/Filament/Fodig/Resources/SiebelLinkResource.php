<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelLinkResource\Pages;
use App\Filament\Fodig\Resources\SiebelLinkResource\RelationManagers;
use App\Models\SiebelLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SiebelLinkResource extends Resource
{
    protected static ?string $model = SiebelLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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
                Forms\Components\TextInput::make('source_field')
                    ->maxLength(100),
                Forms\Components\TextInput::make('destination_field')
                    ->maxLength(100),
                Forms\Components\TextInput::make('inter_parent_column')
                    ->maxLength(100),
                Forms\Components\TextInput::make('inter_child_column')
                    ->maxLength(100),
                Forms\Components\Toggle::make('inter_child_delete'),
                Forms\Components\TextInput::make('primary_id_field')
                    ->maxLength(100),
                Forms\Components\TextInput::make('cascade_delete')
                    ->required()
                    ->maxLength(50),
                Forms\Components\Textarea::make('search_specification')
                    ->maxLength(500),
                Forms\Components\TextInput::make('association_list_sort_specification')
                    ->maxLength(100),
                Forms\Components\Toggle::make('no_associate'),
                Forms\Components\Toggle::make('no_delete'),
                Forms\Components\Toggle::make('no_insert'),
                Forms\Components\Toggle::make('no_inter_delete'),
                Forms\Components\Toggle::make('no_update'),
                Forms\Components\Toggle::make('visibility_auto_all'),
                Forms\Components\TextInput::make('visibility_rule_applied')
                    ->maxLength(50),
                Forms\Components\TextInput::make('visibility_type')
                    ->maxLength(50),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(400),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(50),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('parent_business_component_id')
                    ->relationship('parentBusinessComponent', 'name')
                    ->nullable(),
                Forms\Components\Select::make('child_business_component_id')
                    ->relationship('childBusinessComponent', 'name')
                    ->nullable(),
                Forms\Components\Select::make('inter_table_id')
                    ->relationship('interTable', 'name')
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
                Tables\Columns\TextColumn::make('source_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('destination_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('inter_parent_column')
                    ->sortable(),
                Tables\Columns\TextColumn::make('inter_child_column')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inter_child_delete')
                    ->sortable(),
                Tables\Columns\TextColumn::make('primary_id_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('cascade_delete')
                    ->sortable(),
                Tables\Columns\TextColumn::make('search_specification')
                    ->sortable(),
                Tables\Columns\TextColumn::make('association_list_sort_specification')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_associate')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_delete')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_insert')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_inter_delete')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_update')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('visibility_auto_all')
                    ->sortable(),
                Tables\Columns\TextColumn::make('visibility_rule_applied')
                    ->sortable(),
                Tables\Columns\TextColumn::make('visibility_type')
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
                Tables\Columns\TextColumn::make('parentBusinessComponent.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('childBusinessComponent.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('interTable.name')
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
                10, 25, 50, 100,
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
            'index' => Pages\ListSiebelLinks::route('/'),
            'create' => Pages\CreateSiebelLink::route('/create'),
            'view' => Pages\ViewSiebelLink::route('/{record}'),
            'edit' => Pages\EditSiebelLink::route('/{record}/edit'),
        ];
    }
}
