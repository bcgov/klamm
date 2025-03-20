<?php

namespace App\Filament\Fodig\Resources;

use App\Filament\Fodig\Resources\SiebelFieldResource\Pages;
use App\Filament\Fodig\Resources\SiebelFieldResource\RelationManagers;
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

class SiebelFieldResource extends Resource
{
    protected static ?string $model = SiebelField::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Siebel Tables';
    protected static ?string $recordTitleAttribute = 'name';
    protected static int $globalSearchResultsLimit = 25;


    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'table.name',
            'businessComponent.name',
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
            'Business Component' => $record->businessComponent?->name,
            'Table Column' => $record->table_column,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['table', 'businessComponent'])
            ->when(
                request('search'),
                fn(Builder $query, $search) => $query->orWhere('table_column', 'ilike', "%{$search}%")
                    ->orWhere('multi_value_link', 'ilike', "%{$search}%")
                    ->orWhere('multi_value_link_field', 'ilike', "%{$search}%")
                    ->orWhere('join', 'ilike', "%{$search}%")
                    ->orWhere('join_column', 'ilike', "%{$search}%")
                    ->orWhere('calculated_value', 'ilike', "%{$search}%")
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
                Forms\Components\Select::make('business_component_id')
                    ->relationship('businessComponent', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\Select::make('table_id')
                    ->relationship('table', 'name')
                    ->searchable()
                    ->nullable(),
                Forms\Components\TextInput::make('table_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('multi_value_link_field')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\TextInput::make('join_column')
                    ->maxLength(400)
                    ->nullable(),
                Forms\Components\Textarea::make('calculated_value')
                    ->autosize()
                    ->columnSpanFull()
                    ->nullable(),
                Forms\Components\Toggle::make('has_field_references')
                    ->label('Has Field References')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('is_referenced')
                    ->label('Referenced by Other Fields')
                    ->disabled()
                    ->dehydrated(false),
                Forms\Components\Toggle::make('has_list_of_values')
                    ->label('Has List of Values')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('businessComponent.name')
                    ->label('Business Component')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('table_column')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('multi_value_link')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('multi_value_link_field')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('join')
                    ->sortable(),
                Tables\Columns\TextColumn::make('join_column')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_value')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_field_references')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_referenced')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('has_list_of_values')
                    ->boolean()
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('business_component_id')
                    ->label('Business Component')
                    ->multiple()
                    ->searchable()
                    ->attribute('businessComponent.name')
                    ->relationship('businessComponent', 'name'),
                Tables\Filters\SelectFilter::make('table_id')
                    ->label('Table')
                    ->multiple()
                    ->searchable()
                    ->attribute('table.name')
                    ->relationship('table', 'name'),
                Tables\Filters\SelectFilter::make('table_column')
                    ->label('Table Column')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('multi_value_link')
                    ->label('Multi Value Link')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('multi_value_link_field')
                    ->label('Multi Value Link Field')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('join')
                    ->label('Join')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('join_column')
                    ->label('Join Column')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('calculated_value')
                    ->label('Calculated Value')
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('has_field_references')
                    ->label('Has Field References')
                    ->options([
                        'references' => 'Has Field References',
                        'noReferences' => 'Does not Have Field References',

                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'references' => $query->whereHas('references'),
                            'noReferences' => $query->whereDoesntHave('references'),

                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('is_referenced')
                    ->label('Referenced by Other Fields')
                    ->options([
                        'isReferenced' => 'Is referenced by Other Fields',
                        'isnotReferenced' => 'Is not referenced by Other Fields',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'isReferenced' => $query->whereHas('referencedBy'),
                            'isnotReferenced' => $query->whereDoesntHave('referencedBy'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('has_list_of_values')
                    ->label('Has List of Values')
                    ->options([
                        'values' => 'References Value from List of Values',
                        'noValues' => 'Does not Reference Value from List of Values',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'values' => $query->whereHas('values'),
                            'noValues' => $query->whereDoesntHave('values'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            RelationManagers\ReferencesRelationManager::class,
            RelationManagers\ReferencedByRelationManager::class,
            RelationManagers\ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiebelFields::route('/'),
            'create' => Pages\CreateSiebelField::route('/create'),
            'view' => Pages\ViewSiebelField::route('/{record}'),
            'edit' => Pages\EditSiebelField::route('/{record}/edit'),
        ];
    }
}
