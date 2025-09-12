<?php

namespace App\Filament\Forms\Resources;

use App\Helpers\DataBindingsHelper;
use App\Models\DataBindingMapping;
use App\Models\FormMetadata\FormDataSource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Filament\Forms\Resources\DataBindingMappingResource\Pages;

class DataBindingMappingResource extends Resource
{
    protected static ?string $model = DataBindingMapping::class;

    protected static ?string $navigationGroup = 'Form Building';
    protected static ?string $navigationLabel = 'Databinding Mappings';
    protected static ?string $slug = 'data-bindings';
    protected static ?string $navigationIcon = 'heroicon-o-link';

    // Admin-only gate
    public static function canViewAny(): bool        { return auth()->user()?->hasRole('admin') ?? false; }
    public static function canCreate(): bool         { return self::canViewAny(); }
    public static function canEdit(Model $record): bool   { return self::canViewAny(); }
    public static function canDelete(Model $record): bool { return self::canViewAny(); }
    public static function canDeleteAny(): bool      { return self::canViewAny(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Details')->columns(2)->schema([
                TextInput::make('label')
                    ->label('Label')
                    ->required()
                    ->maxLength(255),

                TextInput::make('endpoint')
                    ->label('Endpoint')
                    ->helperText('ICM Endpoint'),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Binding')->columns(2)->schema([

                // Data source dropdown which comes from Databinding Sources
                Select::make('data_source')
                    ->label('Data source')
                    ->required()
                    ->searchable()
                    ->preload()
                    // options from form_data_sources.name
                    ->options(fn () =>
                        FormDataSource::query()
                            ->orderBy('name')
                            ->pluck('name', 'name')
                            ->all()
                    )
                    // keep JSONPath preview synced
                    ->live(debounce: 400)
                    ->afterStateUpdated(function (Set $set, $state, Get $get) {
                        $set('data_path', self::composeJsonPath(
                            (string) $state,
                            (string) $get('path_label')
                        ));
                    })
                    // ensure chosen value exists in form_data_sources.name
                    ->rule(function () {
                        $table = (new FormDataSource)->getTable();
                        return Rule::exists($table, 'name');
                    }),

                // Path label: free text + dynamic datalist scoped by current data_source; preview updates on blur
                DataBindingsHelper::pathLabelField(
                    sourceField: 'data_source',
                    sourceIsId: false,
                    targetPathField: 'data_path',
                ),

                Grid::make(2)->schema([
                    TextInput::make('data_path')
                        ->label('Data path')
                        ->disabled()
                        ->dehydrated(false)
                        ->helperText("Composed as: \$['{Data source}']['{Path label}']")
                        ->afterStateHydrated(function (Set $set, Get $get) {
                            $set('data_path', self::composeJsonPath(
                                (string) $get('data_source'),
                                (string) $get('path_label')
                            ));
                        }),

                    TextInput::make('repeating_path')
                        ->label('Repeating path')
                        ->helperText("Repeating path for container element type e.g. $.['{Data source}'].[*]"),
                ])->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->label('Label')->searchable()->sortable(),
                TextColumn::make('description')->label('Description')->limit(40)->toggleable(),
                TextColumn::make('data_source')->label('Data source')->sortable()->searchable(),
                TextColumn::make('endpoint')->label('End point')->limit(40)->toggleable(),
                TextColumn::make('path_label')->label('Path label')->searchable(),
                TextColumn::make('data_path')->label('Data path')->wrap(),
                TextColumn::make('repeating_path')->label('Repeating path')->wrap(),
                TextColumn::make('updated_at')->label('Updated')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('data_source')
                    ->label('Data source')
                    ->multiple()
                    ->options(
                        DataBindingMapping::query()
                            ->whereNotNull('data_source')
                            ->distinct()
                            ->orderBy('data_source')
                            ->pluck('data_source', 'data_source')
                            ->all()
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDataBindingMappings::route('/'),
            'create' => Pages\CreateDataBindingMapping::route('/create'),
            'view'   => Pages\ViewDataBindingMapping::route('/{record}'),
            'edit'   => Pages\EditDataBindingMapping::route('/{record}/edit'),
        ];
    }

    // build JSONPath from source + label without mutating inputs
    public static function composeJsonPath(?string $source, ?string $label): string
    {
        $s = trim((string) $source);
        $l = trim((string) $label);

        if ($s === '' || $l === '') {
            return '';
        }

        // normalise minimally for preview safety
        $s = str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $s);
        $l = str_replace(['"', "\r", "\n"], ["'", ' ', ' '], $l);

        return "$.['{$s}'].['{$l}']";
        }
}
