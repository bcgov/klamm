<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormFieldResource\Pages;
use App\Filament\Forms\Resources\FormFieldResource\RelationManagers\FormInstanceFieldsRelationManager;
use App\Helpers\DateFormatHelper;
use App\Models\FormField;
use App\Models\FormDataSource;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use App\Models\DataType;
use App\Models\SelectOptions;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Filters\SelectFilter;
use App\Http\Middleware\CheckRole;

class FormFieldResource extends Resource
{
    protected static ?string $model = FormField::class;
    protected static ?string $navigationLabel = 'Fields';
    protected static ?string $navigationIcon = 'icon-text-cursor-input';

    protected static ?string $navigationGroup = 'Form Building';


    public static function shouldRegisterNavigation(): bool
    {
        return CheckRole::hasRole(request(), 'admin', 'form-developer');
    }



    public static function form(Form $form): Form
    {
        $validationOptions = [
            'minValue' => 'Minimum Value',
            'maxValue' => 'Maximum Value',
            'minLength' => 'Minimum Length',
            'maxLength' => 'Maximum Length',
            'required' => 'Required',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'javascript' => 'JavaScript',
        ];

        $selectOptions = SelectOptions::all()->keyBy('id');
        $dataTypes = DataType::all()->keyBy('id');
        $isDate = fn($get) => isset($dataTypes[$get('data_type_id')]) && $dataTypes[$get('data_type_id')]->name === 'date';

        return $form
            ->columns(6)
            ->schema([
                TextInput::make('name')
                    ->unique(ignoreRecord: true)
                    ->columnSpan(2)
                    ->required(),
                TextInput::make('label')
                    ->columnSpan(2)
                    ->required(),
                Select::make('data_type_id')
                    ->relationship('dataType', 'name')
                    ->columnSpan(2)
                    ->required()
                    ->live(),
                RichEditor::make('value')
                    ->label('Field Value')
                    ->visible(function (callable $get) use ($dataTypes) {
                        $dataTypeId = $get('data_type_id');
                        return isset($dataTypes[$dataTypeId]) && $dataTypes[$dataTypeId]->name === 'text-info';
                    })
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'strike',
                        'link',
                        'h1',
                        'h2',
                        'h3',
                        'blockquote',
                        'codeBlock',
                        'bulletList',
                        'orderedList',
                        'undo',
                        'redo',
                    ])
                    ->live()
                    ->columnSpanFull(),
                Builder::make('select_option_instances')
                    ->label('Select Option Instances')
                    ->columnSpanFull()
                    ->reorderable()
                    ->blockNumbers(false)
                    ->collapsible()
                    ->collapsed(true)
                    ->live()
                    ->reactive()
                    ->visible(fn($get) => isset($dataTypes[$get('data_type_id')]) && in_array($dataTypes[$get('data_type_id')]->name, ['radio', 'dropdown']))
                    ->blocks([
                        Block::make('select_option_instance')
                            ->label(
                                fn(?array $state): string =>
                                isset($state['select_option_id']) && $selectOptions->has($state['select_option_id'])
                                    ? $selectOptions[$state['select_option_id']]->label
                                    . ' | ' . $selectOptions[$state['select_option_id']]->name
                                    . ' | value: ' . $selectOptions[$state['select_option_id']]->value
                                    : 'New Option'
                            )
                            ->schema([
                                Select::make('select_option_id')
                                    ->label('Option')
                                    ->options($selectOptions->map(function ($option) {
                                        return "{$option->label} | {$option->name} | value: {$option->value}";
                                    })->toArray())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live(),
                            ])
                    ]),
                Fieldset::make('Data Bindings')
                    ->columnSpanFull()
                    ->columns(6)
                    ->schema(([
                        Select::make('data_binding')
                            ->label('Source')
                            ->options(FormDataSource::pluck('name', 'name'))
                            ->columnSpan(fn($get) => $isDate($get) ? 2 : 3),
                        TextInput::make('data_binding_path')
                            ->label('Path')
                            ->columnSpan(fn($get) => $isDate($get) ? 2 : 3),
                        Select::make('date_format')
                            ->visible($isDate)
                            ->columnSpan(2)
                            ->options(DateFormatHelper::dateFormats()),
                    ])),
                TextInput::make('mask')
                    ->columnSpan(3),
                Select::make('field_group_id')
                    ->columnSpan(3)
                    ->multiple()
                    ->preload()
                    ->relationship('fieldGroups', 'name'),
                Repeater::make('validations')
                    ->label('Validations')
                    ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                    ->relationship('validations')
                    ->defaultItems(0)
                    ->columnSpanFull()
                    ->addActionAlignment(Alignment::Start)
                    ->schema([
                        Select::make('type')
                            ->label('Validation Type')
                            ->options($validationOptions)
                            ->reactive()
                            ->required(),
                        TextInput::make('value')
                            ->label('Value'),
                        TextInput::make('error_message')
                            ->label('Error Message'),
                    ])
                    ->collapsed(),
                Textarea::make('help_text')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('dataType.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('data_binding')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('fieldGroups.name')
                    ->sortable()
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
                SelectFilter::make('data_binding')
                    ->label('Data Binding')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->options(function () {  // Fetch unique values from the 'data_binding' column
                        return \App\Models\FormField::query()
                            ->distinct()
                            ->pluck('data_binding', 'data_binding')
                            ->filter()
                            ->toArray();
                    }),
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

    public static function getRelations(): array
    {
        return [
            FormInstanceFieldsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormFields::route('/'),
            'create' => Pages\CreateFormField::route('/create'),
            'view' => Pages\ViewFormField::route('/{record}'),
            'edit' => Pages\EditFormField::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Field';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Fields';
    }
}
