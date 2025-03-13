<?php

namespace App\Filament\Bre\Resources;

use Closure;
use App\Filament\Bre\Resources\FieldResource\Pages;
use App\Filament\Bre\Resources\DataValidationResource;
use App\Models\BREDataType;
use App\Models\BREDataValidation;
use App\Models\BREField;
use App\Models\ICMCDWField;
use App\Models\SiebelBusinessObject;
use App\Filament\Fodig\Resources\SiebelBusinessObjectResource;
use App\Filament\Fodig\Resources\SiebelBusinessComponentResource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use App\Filament\Exports\BREFieldExporter;
use Filament\Tables\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Colors\Color;
use Filament\GlobalSearch\Actions\Action;

class FieldResource extends Resource
{
    protected static ?string $model = BREField::class;
    protected static ?string $navigationLabel = 'BRE Rule Fields';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $recordTitleAttribute = 'name';
    protected static int $globalSearchResultsLimit = 25;

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'label',
            'description',
            'breDataType.name',
            'breDataValidation.name',
            'breFieldGroups.name',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Label' => $record->label,
            'Data Type' => $record->breDataType?->name,
            'Data Validation' => $record->breDataValidation?->name,
            'Field Groups' => $record->fieldGroupNames,
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['breDataType', 'breDataValidation', 'breFieldGroups', 'childFields'])
            ->when(
                request('search'),
                fn(Builder $query, $search) => $query->orWhere('help_text', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
            );
    }

    public static function getGlobalSearchResultActions(Model $record): array
    {
        return [
            Action::make('view')
                ->url(static::getUrl('view', ['record' => $record->name])),
            Action::make('edit')
                ->url(static::getUrl('edit', ['record' => $record->name])),
        ];
    }

    private static string $badgeTemplate = '
        <a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
            <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                <span class="grid">
                    <span class="truncate">%s</span>
                </span>
            </span>
        </a>';

    private static function formatBadge(string $url, string $text): string
    {
        return sprintf(static::$badgeTemplate, $url, e($text));
    }

    public static function form(Form $form): Form
    {
        $dataTypeIdsForChildFields = BREDataType::where('name', 'LIKE', '%array%')
            ->pluck('id')
            ->toArray();

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('label'),
                Forms\Components\Textarea::make('help_text')
                    ->columnSpanFull(),
                Forms\Components\Select::make('data_type_id')
                    ->relationship('breDataType', 'name')
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $set) {
                        $set('child_fields', null);
                    }),
                Forms\Components\Select::make('field_group_id')
                    ->multiple()
                    ->relationship('breFieldGroups', 'name'),
                Forms\Components\Select::make('data_validation_id')
                    ->relationship('breDataValidation', 'name'),
                Forms\Components\Select::make('child_fields')
                    ->multiple()
                    ->relationship('childFields', 'name')
                    ->visible(fn(callable $get) => in_array($get('data_type_id'), $dataTypeIdsForChildFields)) // Check if data_type_id is in the array of matching IDs
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Select::make('input_rules')
                    ->label('Used as Inputs by Rules:')
                    ->multiple()
                    ->relationship('breInputs', 'name'),
                Forms\Components\Select::make('output_fields')
                    ->label('Returned as Outputs by Rules:')
                    ->multiple()
                    ->relationship('breOutputs', 'name'),
                Forms\Components\Select::make('icmcdwFieldsTest')
                    ->label('Related ICM CDW Fields:')
                    ->multiple()
                    ->relationship(
                        name: 'icmcdwFields',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')->orderBy('field')->orderBy('panel_type')->orderBy('entity')->orderBy('subject_area')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name} - {$record->field} - {$record->panel_type} - {$record->entity} - {$record->subject_area}")
                    ->searchable(['name', 'field', 'panel_type', 'entity', 'subject_area']),
                Forms\Components\Select::make('siebelBusinessObjectField')
                    ->label('Related Siebel Business Objects:')
                    ->multiple()
                    ->relationship(
                        name: 'siebelBusinessObjects',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name}")
                    ->searchable(['name', 'repository_name', 'comments']),
                Forms\Components\Select::make('siebelBusinessComponentField')
                    ->label('Related Siebel Business Components:')
                    ->multiple()
                    ->relationship(
                        name: 'siebelBusinessComponents',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name}")
                    ->searchable(['name', 'repository_name', 'comments']),
                Forms\Components\Select::make('siebelAppletField')
                    ->label('Related Siebel Business Applets:')
                    ->multiple()
                    ->relationship(
                        name: 'siebelApplets',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name}")
                    ->searchable(['name', 'repository_name', 'comments']),
                Forms\Components\Select::make('siebelTableField')
                    ->label('Related Siebel Business Tables:')
                    ->multiple()
                    ->relationship(
                        name: 'siebelTables',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name}")
                    ->searchable(['name', 'repository_name', 'comments']),
                Forms\Components\Select::make('siebelFieldField')
                    ->label('Related Siebel Business Fields:')
                    ->multiple()
                    ->relationship(
                        name: 'siebelFields',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->name}")
                    ->searchable(['name', 'multi_value_link', 'join_column', 'calculated_value']),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('label'),
                TextEntry::make('help_text'),
                TextEntry::make('breDataType.name')
                    ->label('Data Type'),
                TextEntry::make('breDataValidation.name')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->breDataValidation) {
                            return '';
                        }

                        return new HtmlString(
                            static::formatBadge(
                                DataValidationResource::getUrl('view', ['record' => $record->breDataValidation->id]),
                                $record->breDataValidation->name
                            )
                        );
                    })
                    ->html()
                    ->label('Data Validation'),
                TextEntry::make('childFields')
                    ->label('Child Rule Fields')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->childFields->map(function ($field) {
                                return static::formatBadge(
                                    static::getUrl('view', ['record' => $field->name]),
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html(),
                TextEntry::make('breFieldGroups.name')
                    ->label('Field Groups'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('breInputs.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breInputs->map(function ($rule) {
                                return static::formatBadge(
                                    RuleResource::getUrl('view', ['record' => $rule->name]),
                                    $rule->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Used as Inputs by Rules'),
                TextEntry::make('breOutputs.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breOutputs->map(function ($rule) {
                                return static::formatBadge(
                                    RuleResource::getUrl('view', ['record' => $rule->name]),
                                    $rule->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Returned as Outputs by Rules'),
                TextEntry::make('icmcdwFields.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->icmcdwFields->map(function ($field) {
                                return static::formatBadge(
                                    ICMCDWFieldResource::getUrl('view', ['record' => $field->id]),
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related ICM CDW Fields')
                    ->columnSpanFull(),
                TextEntry::make('siebelBusinessObjects.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->siebelBusinessObjects->map(function ($field) {
                                return static::formatBadge(
                                    "/fodig/siebel-business-objects/{$field->id}",
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related Siebel Business Objects'),
                TextEntry::make('siebelBusinessComponents.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->siebelBusinessComponents->map(function ($field) {
                                return static::formatBadge(
                                    "/fodig/siebel-business-components/{$field->id}",
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related Siebel Business Components'),
                TextEntry::make('siebelApplets.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->siebelApplets->map(function ($field) {
                                return static::formatBadge(
                                    "/fodig/siebel-applets/{$field->id}",
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related Siebel Business Applets'),
                TextEntry::make('siebelTables.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->siebelTables->map(function ($field) {
                                return static::formatBadge(
                                    "/fodig/siebel-tables/{$field->id}",
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related Siebel Business Tables'),
                TextEntry::make('siebelFields.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->siebelFields->map(function ($field) {
                                return static::formatBadge(
                                    "/fodig/siebel-fields/{$field->id}",
                                    $field->name
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related Siebel Business Fields'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->tooltip(fn(Model $record): string => "{$record->description}")
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->sortable()
                    ->tooltip(fn(Model $record): string => "{$record->description}")
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breDataType.name')
                    ->label('Data Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('breDataValidation.name')
                    ->label('Data Validations')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('childFields.name')
                    ->label('Child Rule Fields')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fieldGroupNames')
                    ->label('Field Groups')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('input_output_type')
                    ->label('Input/Output?')
                    ->default(function ($record) {
                        return $record->getInputOutputType();
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'input' => 'primary',
                        'output' => 'danger',
                        'input/output' => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breInputs.name')
                    ->label('Rules: Inputs')
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Rules: Outputs')
                    ->searchable()
                    ->badge()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('icmcdwFields.name')
                    ->label('Related ICM CDW Fields')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siebelBusinessObjects.name')
                    ->label('Related Siebel Business Objects')
                    ->searchable()
                    ->badge()
                    ->color(Color::hex('#E9C46A'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siebelBusinessComponents.name')
                    ->label('Related Siebel Business Components')
                    ->searchable()
                    ->badge()
                    ->color(Color::hex('#397367'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siebelApplets.name')
                    ->label('Related Siebel Business Applets')
                    ->searchable()
                    ->badge()
                    ->color(Color::hex('#2A9D8F'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siebelTables.name')
                    ->label('Related Siebel Business Tables')
                    ->searchable()
                    ->badge()
                    ->color(Color::hex('#F4A261'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('siebelFields.name')
                    ->label('Related Siebel Business Fields')
                    ->searchable()
                    ->badge()
                    ->color(Color::hex('#2A9D8F'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // disabled to enable exports
            // ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('data_type_id')
                    ->label('Data Type')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('breDataType.name'))
                    ->relationship('breDataType', 'name'),
                Tables\Filters\SelectFilter::make('data_validation_id')
                    ->label('Related BRE Fields:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('breDataValidation.name'))
                    ->relationship('breDataValidation', 'name'),
                Tables\Filters\SelectFilter::make('child_fields')
                    ->label('Child Fields')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('childFields.name'))
                    ->relationship('childFields', 'name'),
                Tables\Filters\SelectFilter::make('field_group_id')
                    ->label('Field Groups')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('fieldGroupNames'))
                    ->relationship('breFieldGroups', 'name'),
                Tables\Filters\SelectFilter::make('input_output_type')
                    ->label('Used as rule Input or Output?')
                    ->options([
                        'input' => 'Input Only',
                        'output' => 'Output Only',
                        'input/output' => 'Input/Output',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return match ($data['value']) {
                            'input' => $query->whereHas('breInputs')
                                ->whereDoesntHave('breOutputs'),
                            'output' => $query->whereHas('breOutputs')
                                ->whereDoesntHave('breInputs'),
                            'input/output' => $query->whereHas('breInputs')
                                ->whereHas('breOutputs'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('input_rules')
                    ->label('Used as Inputs by Rules:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('input_output_type'))
                    ->relationship('breInputs', 'name'),
                Tables\Filters\SelectFilter::make('output_fields')
                    ->label('Returned as Outputs by Rules:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('input_output_type'))
                    ->relationship('breOutputs', 'name'),
                Tables\Filters\SelectFilter::make('icmcdw_fields')
                    ->label('Related ICM CDW Fields:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('icmcdwFields.name'))
                    ->relationship('icmcdwFields', 'name'),
                Tables\Filters\SelectFilter::make('siebel_business_objects')
                    ->label('Related Siebel Business Objects:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('siebelBusinessObjects.name'))
                    ->relationship('siebelBusinessObjects', 'name'),
                Tables\Filters\SelectFilter::make('siebel_business_components')
                    ->label('Related Siebel Business Components:')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->attribute(('siebelBusinessComponents.name'))
                    ->relationship('siebelBusinessComponents', 'name'),
                Tables\Filters\SelectFilter::make('siebel_business_objects_existence')
                    ->label('Siebel Business Objects Status')
                    ->options([
                        'with' => 'Has Siebel Business Objects',
                        'without' => 'No Siebel Business Objects',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return match ($data['value']) {
                            'with' => $query->whereHas('siebelBusinessObjects'),
                            'without' => $query->whereDoesntHave('siebelBusinessObjects'),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('siebel_business_components_existence')
                    ->label('Siebel Business Components Status')
                    ->options([
                        'with' => 'Has Siebel Business Components',
                        'without' => 'No Siebel Business Components',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return match ($data['value']) {
                            'with' => $query->whereHas('siebelBusinessComponents'),
                            'without' => $query->whereDoesntHave('siebelBusinessComponents'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->label('Export BRE Rule Fields')
                    ->exporter(BREFieldExporter::class)
                    ->fileName(fn(Export $export): string => "BRE-Rule-Fields-{$export->getKey()}"),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFields::route('/'),
            'create' => Pages\CreateField::route('/create'),
            'view' => Pages\ViewField::route('/{record:name}'),
            'edit' => Pages\EditField::route('/{record:name}/edit'),
        ];
    }
}
