<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelBusinessComponentResource\Pages;
use App\Filament\Fodig\Resources\SiebelBusinessComponentResource\RelationManagers;
use App\Filament\Fodig\Resources\SiebelBusinessComponentResource\RelationManagers\SiebelAppletsRelationManager;
use App\Filament\Fodig\Resources\SiebelBusinessComponentResource\RelationManagers\SiebelFieldsRelationManager;
use App\Models\SiebelBusinessComponent;
use App\Models\SiebelField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\GlobalSearch\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class SiebelBusinessComponentResource extends Resource
{
    protected static ?string $model = SiebelBusinessComponent::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';
    protected static ?string $recordTitleAttribute = 'name';
    protected static int $globalSearchResultsLimit = 25;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'repository_name',
            'table.name',
            'project.name',
            'class.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Table' => $record->table?->name,
            'Project' => $record->project?->name,
            'Class' => $record->class?->name,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['table', 'project', 'class'])
            ->when(
                request('search'),
                fn(Builder $query, $search) => $query->orWhere('repository_name', 'ilike', "%{$search}%")
                    ->orWhere('data_source', 'ilike', "%{$search}%")
                    ->orWhere('comments', 'ilike', "%{$search}%")
            );
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('view')
                ->url(static::getUrl('view', ['record' => $record])),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Select::make('project_id')
                    ->relationship('project', 'name')
                    ->nullable(),
                Forms\Components\Select::make('class_id')
                    ->relationship('class', 'name')
                    ->nullable(),
                Forms\Components\Select::make('table_id')
                    ->relationship('table', 'name')
                    ->nullable(),
                Forms\Components\Toggle::make('changed')
                    ->required(),
                Forms\Components\TextInput::make('repository_name')
                    ->required()
                    ->maxLength(400),
                Forms\Components\Toggle::make('cache_data'),
                Forms\Components\TextInput::make('data_source')
                    ->maxLength(50),
                Forms\Components\Toggle::make('dirty_reads'),
                Forms\Components\Toggle::make('distinct'),
                Forms\Components\TextInput::make('enclosure_id_field')
                    ->maxLength(50),
                Forms\Components\Toggle::make('force_active'),
                Forms\Components\Toggle::make('gen_reassign_act'),
                Forms\Components\TextInput::make('hierarchy_parent_field')
                    ->maxLength(30),
                Forms\Components\Select::make('type')
                    ->options([
                        'Transient' => 'Transient',
                        'Non-Transient' => 'Non-Transient',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('inactive'),
                Forms\Components\Toggle::make('insert_update_all_columns'),
                Forms\Components\Toggle::make('log_changes'),
                Forms\Components\TextInput::make('maximum_cursor_size')
                    ->numeric(),
                Forms\Components\Toggle::make('multirecipient_select'),
                Forms\Components\Toggle::make('no_delete'),
                Forms\Components\Toggle::make('no_insert'),
                Forms\Components\Toggle::make('no_update'),
                Forms\Components\Toggle::make('no_merge'),
                Forms\Components\Toggle::make('owner_delete'),
                Forms\Components\Toggle::make('placeholder'),
                Forms\Components\Toggle::make('popup_visibility_auto_all'),
                Forms\Components\TextInput::make('popup_visibility_type')
                    ->maxLength(30),
                Forms\Components\TextInput::make('prefetch_size')
                    ->numeric(),
                Forms\Components\TextInput::make('recipient_id_field')
                    ->maxLength(30),
                Forms\Components\TextInput::make('reverse_fill_threshold')
                    ->numeric(),
                Forms\Components\Toggle::make('scripted'),
                Forms\Components\Textarea::make('search_specification'),
                Forms\Components\Textarea::make('sort_specification'),
                Forms\Components\TextInput::make('status_field')
                    ->maxLength(100),
                Forms\Components\TextInput::make('synonym_field')
                    ->maxLength(100),
                Forms\Components\TextInput::make('upgrade_ancestor')
                    ->maxLength(200),
                Forms\Components\TextInput::make('xa_attribute_value_bus_comp')
                    ->maxLength(100),
                Forms\Components\TextInput::make('xa_class_id_field')
                    ->maxLength(100),
                Forms\Components\Textarea::make('comments')
                    ->maxLength(500),
                Forms\Components\Toggle::make('object_locked'),
                Forms\Components\TextInput::make('object_language_locked')
                    ->maxLength(10),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('project.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('siebelFields.name')
                    ->searchable()
                    ->badge()
                    ->label('Child Siebel Fields'),
                Tables\Columns\TextColumn::make('siebelApplets.name')
                    ->searchable()
                    ->badge()
                    ->label('Child Siebel Applets'),
                Tables\Columns\TextColumn::make('class.name')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('changed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('repository_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('distinct')
                    ->sortable(),
                Tables\Columns\TextColumn::make('enclosure_id_field')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('force_active')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('gen_reassign_act')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hierarchy_parent_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('inactive')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('insert_update_all_columns')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('log_changes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('maximum_cursor_size')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('multirecipient_select')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_delete')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_insert')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_update')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('no_merge')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('owner_delete')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('placeholder')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('popup_visibility_auto_all')
                    ->sortable(),
                Tables\Columns\TextColumn::make('popup_visibility_type')
                    ->sortable(),
                Tables\Columns\TextColumn::make('prefetch_size')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient_id_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reverse_fill_threshold')
                    ->sortable(),
                Tables\Columns\BooleanColumn::make('scripted')
                    ->sortable(),
                Tables\Columns\TextColumn::make('search_specification')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_specification')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('synonym_field')
                    ->sortable(),
                Tables\Columns\TextColumn::make('upgrade_ancestor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('xa_attribute_value_bus_comp')
                    ->sortable(),
                Tables\Columns\TextColumn::make('xa_class_id_field')
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
                Tables\Columns\TextColumn::make('table.name')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->multiple()
                    ->searchable()
                    ->attribute('project.name')
                    ->relationship('project', 'name'),
                Tables\Filters\SelectFilter::make('class_id')
                    ->label('Class')
                    ->multiple()
                    ->searchable()
                    ->attribute('class.name')
                    ->relationship('class', 'name'),
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Table')
                    ->multiple()
                    ->searchable()
                    ->attribute('table.name')
                    ->relationship('table', 'name'),
                Tables\Filters\SelectFilter::make('siebelFields')
                    ->label('Fields')
                    ->multiple()
                    ->searchable()
                    ->attribute('siebelFields.name')
                    ->relationship('siebelFields', 'name'),
                Tables\Filters\SelectFilter::make('siebelApplets')
                    ->label('Applets')
                    ->multiple()
                    ->searchable()
                    ->attribute('siebelApplets.name')
                    ->relationship('siebelApplets', 'name'),
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
            SiebelFieldsRelationManager::class,
            SiebelAppletsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiebelBusinessComponents::route('/'),
            'create' => Pages\CreateSiebelBusinessComponent::route('/create'),
            'view' => Pages\ViewSiebelBusinessComponent::route('/{record}'),
            'edit' => Pages\EditSiebelBusinessComponent::route('/{record}/edit'),
        ];
    }
}
