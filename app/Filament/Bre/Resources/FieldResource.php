<?php

namespace App\Filament\Bre\Resources;

use Closure;
use App\Filament\Bre\Resources\FieldResource\Pages;
use App\Filament\Bre\Resources\DataValidationResource;
use App\Models\BREDataType;
use App\Models\BREDataValidation;
use App\Models\BREField;
use App\Models\ICMCDWField;
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

class FieldResource extends Resource
{
    protected static ?string $model = BREField::class;
    protected static ?string $navigationLabel = 'BRE Rule Fields';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // protected static ?string $navigationGroup = 'Rule Building';

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
                    ->reactive() // This makes the field listen for changes
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
                    ->searchable(['name', 'field', 'panel_type', 'entity', 'subject_area'])
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
                            sprintf(
                                '<a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
                                <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                <span class="grid">
                                <span class="truncate">%s</span>
                                </span>
                                </span>
                                </a>',
                                DataValidationResource::getUrl('view', ['record' => $record->breDataValidation->id]),
                                e($record->breDataValidation->name)
                            )
                        );
                    })
                    ->html()
                    ->label('Data Validation'),
                TextEntry::make('breFieldGroups.name')
                    ->label('Field Groups'),
                TextEntry::make('description')
                    ->columnSpanFull(),
                TextEntry::make('breInputs.name')
                    ->formatStateUsing(function ($state, $record) {
                        return new HtmlString(
                            $record->breInputs->map(function ($rule) {
                                return sprintf(
                                    '<a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
                                    <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                    <span class="grid">
                                    <span class="truncate">%s</span>
                                    </span>
                                    </span>
                                    </a>',
                                    RuleResource::getUrl('view', ['record' => $rule->name]),
                                    e($rule->name)
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
                                return sprintf(
                                    '<a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
                                    <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                    <span class="grid">
                                    <span class="truncate">%s</span>
                                    </span>
                                    </span>
                                    </a>',
                                    RuleResource::getUrl('view', ['record' => $rule->name]),
                                    e($rule->name)
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
                                return sprintf(
                                    '<a href="%s" style="text-decoration: none; display: inline-block; margin: 2px;">
                                    <span class="fi-badge flex items-center justify-center gap-x-1 rounded-md text-xs font-medium ring-1 ring-inset px-2 min-w-[theme(spacing.6)] py-1 fi-color-custom bg-custom-50 text-custom-600 ring-custom-600/10 dark:bg-custom-400/10 dark:text-custom-400 dark:ring-custom-400/30 fi-color-primary" style="--c-50:var(--primary-50);--c-400:var(--primary-400);--c-600:var(--primary-600);">
                                    <span class="grid">
                                    <span class="truncate">%s</span>
                                    </span>
                                    </span>
                                    </a>',
                                    ICMCDWFieldResource::getUrl('view', ['record' => $field->id]),
                                    e($field->name)
                                );
                            })->join('')
                        );
                    })
                    ->html()
                    ->label('Related ICM CDW Fields')
                    ->columnSpanFull(),
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
                Tables\Columns\TextColumn::make('childFields')
                    ->label('Child Fields')
                    ->formatStateUsing(function ($record) {
                        if ($record->childFields && $record->childFields->isNotEmpty()) {
                            return $record->childFields->pluck('name')->join(', ');
                        }
                        return '';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fieldGroupNames')
                    ->label('Field Groups')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('input_output_type')
                    ->label('Input/Output?')
                    ->default(function ($record) {
                        return $record->getInputOutputType();
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breInputs.name')
                    ->label('Rules: Inputs')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('breOutputs.name')
                    ->label('Rules: Outputs')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('icmcdwFields.name')
                    ->label('Related ICM CDW Fields')
                    ->sortable()
                    ->searchable()
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
            ->defaultSort('name')
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
