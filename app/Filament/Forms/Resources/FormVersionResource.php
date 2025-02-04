<?php

namespace App\Filament\Forms\Resources;

use App\Filament\Forms\Resources\FormVersionResource\Pages;
use App\Models\FormVersion;
use App\Models\FormField;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\Alignment;

class FormVersionResource extends Resource
{
    protected static ?string $model = FormVersion::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static bool $shouldRegisterNavigation = true;


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

        $conditionalOptions = [
            'visibility' => 'Visibility',
            'calculatedValue' => 'Calculated Value',
            'saveOnSubmit' => 'Save on Submit',
            'readOnly' => 'Read Only',
        ];

        return $form
            ->schema([
                Select::make('form_id')
                    ->relationship('form', 'form_id_title')
                    ->required()
                    ->reactive()
                    ->preload()
                    ->searchable()
                    ->default(request()->query('form_id_title')),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'testing' => 'Testing',
                        'archived' => 'Archived',
                        'published' => 'Published',
                    ])
                    ->required(),
                Section::make('Form Properties')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->compact()
                    ->schema([
                        Fieldset::make('Requester Information')
                            ->schema([
                                TextInput::make('form_requester_name')
                                    ->label('Name'),
                                TextInput::make('form_requester_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Requester Information'),
                        Fieldset::make('Approver Information')
                            ->schema([
                                TextInput::make('form_approver_name')
                                    ->label('Name'),
                                TextInput::make('form_approver_email')
                                    ->label('Email')
                                    ->email(),
                            ])
                            ->label('Approver Information'),
                        Select::make('deployed_to')
                            ->label('Deployed To')
                            ->options([
                                'dev' => 'Development',
                                'test' => 'Testing',
                                'prod' => 'Production',
                            ])
                            ->columnSpan(1)
                            ->nullable()
                            ->afterStateUpdated(fn(callable $set) => $set('deployed_at', now())),
                        DateTimePicker::make('deployed_at')
                            ->label('Deployment Date')
                            ->columnSpan(1),
                        Select::make('form_data_sources')
                            ->multiple()
                            ->preload()
                            ->columnSpan(1)
                            ->relationship('formDataSources', 'name'),
                        Textarea::make('comments')
                            ->label('Comments')
                            ->columnSpanFull()
                            ->maxLength(500),
                    ]),
                Builder::make('components')
                    ->label('Form Components')
                    ->addBetweenActionLabel('Insert between elements')
                    ->columnSpan(2)
                    ->collapsible()
                    ->collapsed(true)
                    ->blockNumbers(false)
                    ->cloneable()
                    ->blocks([
                        Block::make('form_field')
                            ->label(function (?array $state): string {
                                if ($state === null) {
                                    return 'Field';
                                }
                                $field = FormField::find($state['form_field_id']) ?: null;
                                if ($field) {
                                    $label = '';
                                    if ($state['customize_label'] !== 'hide') {
                                        $label .= ($state['custom_label'] ?? $field->label ?? 'null') . ' | ';
                                    } else {
                                        $label .= '(label hidden) | ';
                                    }
                                    $label .= ($field->dataType->name ?? '')
                                        . ' | id: ' . ($state['custom_instance_id'] ?? $state['instance_id'] ?? '');
                                    return $label;
                                }
                                return 'New Field';
                            })
                            ->icon('heroicon-o-stop')
                            ->schema([
                                Select::make('form_field_id')
                                    ->label('Form Field')
                                    ->live()
                                    ->options(function () {
                                        // Compose option labels
                                        $options = FormField::pluck('label', 'id');
                                        foreach ($options as $id => $option) {
                                            $field = FormField::find($id) ?: null;
                                            $options[$id] = $option
                                                . ' | ' . $field->dataType->name
                                                . ' | name: ' . ($field->name ?? '');
                                        }
                                        return $options;
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive(),
                                Section::make('Field Properties')
                                    ->collapsible()
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Fieldset::make('Instance ID')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                                            ->label("Default")
                                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                                        Hidden::make('instance_id') // used to populate value in template 
                                                            ->hidden()
                                                            ->default(fn($get) => \App\Helpers\FormTemplateHelper::calculateFieldID($get('../../'))), // Set the sequential default value
                                                        Toggle::make('customize_instance_id')
                                                            ->label('Customize Instance ID')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_instance_id')
                                                            ->label(false)
                                                            ->alphanum()
                                                            ->reactive()
                                                            ->distinct()
                                                            ->visible(fn($get) => $get('customize_instance_id')),
                                                    ]),
                                                Fieldset::make('Label')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('label')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->label ?? 'null'),
                                                        Radio::make('customize_label')
                                                            ->options([
                                                                'default' => 'Use Default',
                                                                'hide' => 'Hide Label',
                                                                'customize' => 'Customize Label'
                                                            ])
                                                            ->default('default')
                                                            ->inline()
                                                            ->inlineLabel(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set) {
                                                                if ($state !== 'customize') {
                                                                    $set('custom_label', null);
                                                                }
                                                            }),
                                                        TextInput::make('custom_label')
                                                            ->label(false)
                                                            ->reactive()
                                                            ->visible(fn($get) => $get('customize_label') == 'customize'),
                                                    ]),
                                                Fieldset::make('Field Value')
                                                    ->visible(fn($get) => FormField::find($get('form_field_id'))?->isValueInputNeededForField() ?? false)
                                                    ->columns(1)
                                                    ->columnSpanFull()
                                                    ->schema([
                                                        Placeholder::make('field_value')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->formFieldValue?->value ?? 'null'),
                                                        Toggle::make('customize_field_value')
                                                            ->label('Customize Field Value')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_field_value')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_field_value')),
                                                    ]),
                                                Fieldset::make('Data Binding')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('data_binding')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding ?? 'null'),
                                                        Toggle::make('customize_data_binding')
                                                            ->label('Customize Data Binding')
                                                            ->inline()
                                                            ->live(),
                                                        Textarea::make('custom_data_binding')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_data_binding')),
                                                    ]),
                                                Fieldset::make('Data Source')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('data_binding_path')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding_path ?? 'null'),
                                                        Toggle::make('customize_data_binding_path')
                                                            ->label('Customize Data Source')
                                                            ->inline()
                                                            ->live(),
                                                        Select::make('custom_data_binding_path')
                                                            ->label(false)
                                                            ->options(FormDataSource::pluck('name', 'name'))
                                                            ->visible(fn($get) => $get('customize_data_binding_path')),
                                                    ]),
                                                Fieldset::make('Styles')
                                                    ->columns(1)
                                                    ->schema([
                                                        Placeholder::make('styles')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->styles ?? 'null'),
                                                        Toggle::make('customize_styles')
                                                            ->label('Customize Styles')
                                                            ->inline()
                                                            ->live(),
                                                        Textarea::make('custom_styles')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_styles')),
                                                    ]),
                                                Fieldset::make('Mask')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('mask')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->mask ?? 'null'),
                                                        Toggle::make('customize_mask')
                                                            ->label('Customize Mask')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_mask')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_mask')),
                                                    ]),
                                                Fieldset::make('Help Text')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('help_text')
                                                            ->label("Default")
                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->help_text ?? 'null'),
                                                        Toggle::make('customize_help_text')
                                                            ->label('Customize Help text')
                                                            ->inline()
                                                            ->live(),
                                                        Textarea::make('custom_help_text')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_help_text')),
                                                    ]),
                                            ]),
                                    ]),
                                Repeater::make('validations')
                                    ->label('Validations')
                                    ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                                    ->collapsible()
                                    ->collapsed()
                                    ->defaultItems(0)
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
                                    ]),
                                Repeater::make('conditionals')
                                    ->label('Conditionals')
                                    ->itemLabel(fn($state): ?string => $conditionalOptions[$state['type']] ?? 'New Conditional')
                                    ->collapsible()
                                    ->collapsed()
                                    ->defaultItems(0)
                                    ->addActionAlignment(Alignment::Start)
                                    ->schema([
                                        Select::make('type')
                                            ->label('Conditional Type')
                                            ->options($conditionalOptions)
                                            ->reactive()
                                            ->required(),
                                        TextInput::make('value')
                                            ->label('Value'),
                                    ]),
                            ]),
                        Block::make('field_group')
                            ->label(function (?array $state): string {
                                if ($state === null) {
                                    return 'Group';
                                }
                                $group = FieldGroup::find($state['field_group_id']);
                                if ($group) {
                                    $label = ($state['group_label'] ?? $group->label ?? '(no label)')
                                        . ' | group '
                                        . ' | id: ' . ($state['custom_instance_id'] ?? $state['instance_id'] ?? '');
                                    return $label;
                                }
                                return 'New Group';
                            })
                            ->icon('heroicon-o-square-2-stack')
                            ->schema([
                                Select::make('field_group_id')
                                    ->label('Field Group')
                                    ->options(function () {
                                        // Compose option labels
                                        $options = FieldGroup::pluck('label', 'id');
                                        foreach ($options as $id => $option) {
                                            $options[$id] = ($option ?? '(no label)') . ' | group | name: ' . (FieldGroup::find($id)->name ?? '');
                                        }
                                        return $options;
                                    })
                                    ->searchable()
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        $fieldGroup = FieldGroup::find($state);
                                        if ($fieldGroup) {
                                            $formFields = $fieldGroup->formFields()->get()->map(function ($field, $index) {
                                                return [
                                                    'form_field_id' => $field->id,
                                                    'label' => $field->label,
                                                    'data_binding_path' => $field->data_binding_path,
                                                    'data_binding' => $field->data_binding,
                                                    'help_text' => $field->help_text,
                                                    'styles' => $field->styles,
                                                    'mask' => $field->mask,
                                                    'validations' => [],
                                                    'conditionals' => [],
                                                    'instance_id' => 'nestedField' . $index + 1,
                                                    'customize_label' => 'default',
                                                    'customize_group_label' => 'default',
                                                ];
                                            })->toArray();
                                            $set('form_fields', $formFields);
                                        }
                                    }),
                                Section::make('Group Properties')
                                    ->collapsible()
                                    ->compact()
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                Fieldset::make('Instance ID')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                                            ->label("Default")
                                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                                        Hidden::make('instance_id') // used to populate value in template 
                                                            ->hidden()
                                                            ->default(fn($get) => \App\Helpers\FormTemplateHelper::calculateFieldID($get('../../'))), // Set the sequential default value
                                                        Toggle::make('customize_instance_id')
                                                            ->label('Customize Instance ID')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_instance_id')
                                                            ->label(false)
                                                            ->alphanum()
                                                            ->reactive()
                                                            ->distinct()
                                                            ->visible(fn($get) => $get('customize_instance_id')),
                                                    ]),
                                                Fieldset::make('Group Label')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('group_label')
                                                            ->label("Default")
                                                            ->content(fn($get) => FieldGroup::find($get('field_group_id'))->label ?? 'null'),
                                                        Radio::make('customize_group_label')
                                                            ->options([
                                                                'default' => 'Use Default',
                                                                'hide' => 'Hide Label',
                                                                'customize' => 'Customize Label'
                                                            ])
                                                            ->default('default')
                                                            ->inline()
                                                            ->inlineLabel(false)
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, callable $set) {
                                                                if ($state !== 'customize') {
                                                                    $set('custom_group_label', null);
                                                                }
                                                            }),
                                                        TextInput::make('custom_group_label')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_group_label') == 'customize'),
                                                    ]),
                                                Toggle::make('repeater')
                                                    ->label('Repeater')
                                                    ->columnSpanFull()
                                                    ->live(),
                                                Fieldset::make('Repeater Item Label')
                                                    ->columns(1)
                                                    ->visible(fn($get) => $get('repeater'))
                                                    ->schema([
                                                        Placeholder::make('repeater_item_label')
                                                            ->label("Default")
                                                            ->content(fn($get) => FieldGroup::find($get('field_group_id'))->repeater_item_label ?? 'null'),
                                                        Toggle::make('customize_repeater_item_label')
                                                            ->label('Customize Repeater Item Label')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_repeater_item_label')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_repeater_item_label')),
                                                    ]),
                                                Fieldset::make('Data Binding')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('data_binding')
                                                            ->label("Default")
                                                            ->content(fn($get) => FieldGroup::find($get('field_group_id'))->data_binding ?? 'null'),
                                                        Toggle::make('customize_data_binding')
                                                            ->label('Customize Data Binding')
                                                            ->inline()
                                                            ->live(),
                                                        TextInput::make('custom_data_binding')
                                                            ->label(false)
                                                            ->visible(fn($get) => $get('customize_data_binding')),
                                                    ]),
                                                Fieldset::make('Data Source')
                                                    ->columns(1)
                                                    ->columnSpan(1)
                                                    ->schema([
                                                        Placeholder::make('data_binding_path')
                                                            ->label("Default")
                                                            ->content(fn($get) => FieldGroup::find($get('field_group_id'))->data_binding_path ?? 'null'),
                                                        Toggle::make('customize_data_binding_path')
                                                            ->label('Customize Data Source')
                                                            ->inline()
                                                            ->live(),
                                                        Select::make('custom_data_binding_path')
                                                            ->label(false)
                                                            ->options(FormDataSource::pluck('name', 'name'))
                                                            ->visible(fn($get) => $get('customize_data_binding_path')),
                                                    ]),
                                                TextInput::make('visibility')
                                                    ->columnSpanFull()
                                                    ->label('Visibility'),
                                            ]),
                                    ]),
                                Builder::make('form_fields')
                                    ->label('Form Fields in Group')
                                    ->addBetweenActionLabel('Insert between fields')
                                    ->collapsible()
                                    ->collapsed(true)
                                    ->blockNumbers(false)
                                    ->columnSpan(2)
                                    ->cloneable()
                                    ->blocks([
                                        Block::make('form_field')
                                            ->label(function (?array $state): string {
                                                if ($state === null) {
                                                    return 'Field';
                                                }
                                                $field = FormField::find($state['form_field_id']) ?: null;
                                                if ($field) {
                                                    $label = '';
                                                    if ($state['customize_label'] !== 'hide') {
                                                        $label .= ($state['custom_label'] ?? $field->label ?? 'null') . ' | ';
                                                    } else {
                                                        $label .= '(label hidden) | ';
                                                    }
                                                    $label .= ($field->dataType->name ?? '')
                                                        . ' | id: ' . ($state['custom_instance_id'] ?? $state['instance_id'] ?? '');
                                                    return $label;
                                                }
                                                return 'New Field';
                                            })
                                            ->icon('heroicon-o-stop')
                                            ->schema([
                                                Select::make('form_field_id')
                                                    ->label('Form Field')
                                                    ->live()
                                                    ->options(function () {
                                                        // Compose option labels
                                                        $options = FormField::pluck('label', 'id');
                                                        foreach ($options as $id => $option) {
                                                            $field = FormField::find($id) ?: null;
                                                            $options[$id] = $option
                                                                . ' | ' . $field->dataType->name
                                                                . ' | name: ' . ($field->name ?? '');
                                                        }
                                                        return $options;
                                                    })
                                                    ->searchable()
                                                    ->required()
                                                    ->reactive(),
                                                Section::make('Nested Field Properties')
                                                    ->collapsible()
                                                    ->compact()
                                                    ->schema([
                                                        Grid::make(2)
                                                            ->schema([
                                                                Fieldset::make('Instance ID')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                                                            ->label("Default")
                                                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                                                        Hidden::make('instance_id') // used to populate value in template 
                                                                            ->hidden()
                                                                            ->default(fn($get) => \App\Helpers\FormTemplateHelper::calculateFieldInGroupID($get('../../'))), // Set the sequential default value
                                                                        Toggle::make('customize_instance_id')
                                                                            ->label('Customize Instance ID')
                                                                            ->inline()
                                                                            ->live(),
                                                                        TextInput::make('custom_instance_id')
                                                                            ->label(false)
                                                                            ->alphanum()
                                                                            ->reactive()
                                                                            ->distinct()
                                                                            ->visible(fn($get) => $get('customize_instance_id')),
                                                                    ]),
                                                                Fieldset::make('Label')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('label')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->label ?? 'null'),
                                                                        Radio::make('customize_label')
                                                                            ->options([
                                                                                'default' => 'Use Default',
                                                                                'hide' => 'Hide Label',
                                                                                'customize' => 'Customize Label'
                                                                            ])
                                                                            ->default('default')
                                                                            ->inline()
                                                                            ->inlineLabel(false)
                                                                            ->live()
                                                                            ->afterStateUpdated(function ($state, callable $set) {
                                                                                if ($state !== 'customize') {
                                                                                    $set('custom_label', null);
                                                                                }
                                                                            }),
                                                                        TextInput::make('custom_label')
                                                                            ->label(false)
                                                                            ->reactive()
                                                                            ->visible(fn($get) => $get('customize_label') == 'customize'),
                                                                    ]),
                                                                Fieldset::make('Field Value')
                                                                    ->visible(fn($get) => FormField::find($get('form_field_id'))?->isValueInputNeededForField() ?? false)
                                                                    ->columns(1)
                                                                    ->columnSpanFull()
                                                                    ->schema([
                                                                        Placeholder::make('field_value')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->formFieldValue?->value ?? 'null'),
                                                                        Toggle::make('customize_field_value')
                                                                            ->label('Customize Field Value')
                                                                            ->inline()
                                                                            ->live(),
                                                                        TextInput::make('custom_field_value')
                                                                            ->label(false)
                                                                            ->visible(fn($get) => $get('customize_field_value')),
                                                                    ]),
                                                                Fieldset::make('Data Binding')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('data_binding')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding ?? 'null'),
                                                                        Toggle::make('customize_data_binding')
                                                                            ->label('Customize Data Binding')
                                                                            ->inline()
                                                                            ->live(),
                                                                        Textarea::make('custom_data_binding')
                                                                            ->label(false)
                                                                            ->visible(fn($get) => $get('customize_data_binding')),
                                                                    ]),
                                                                Fieldset::make('Data Source')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('data_binding_path')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->data_binding_path ?? 'null'),
                                                                        Toggle::make('customize_data_binding_path')
                                                                            ->label('Customize Data Source')
                                                                            ->inline()
                                                                            ->live(),
                                                                        Select::make('custom_data_binding_path')
                                                                            ->label(false)
                                                                            ->options(FormDataSource::pluck('name', 'name'))
                                                                            ->visible(fn($get) => $get('customize_data_binding_path')),
                                                                    ]),
                                                                Fieldset::make('Styles')
                                                                    ->columns(1)
                                                                    ->schema([
                                                                        Placeholder::make('styles')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->styles ?? 'null'),
                                                                        Toggle::make('customize_styles')
                                                                            ->label('Customize Styles')
                                                                            ->inline()
                                                                            ->live(),
                                                                        Textarea::make('custom_styles')
                                                                            ->label(false)
                                                                            ->visible(fn($get) => $get('customize_styles')),
                                                                    ]),
                                                                Fieldset::make('Mask')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('mask')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->mask ?? 'null'),
                                                                        Toggle::make('customize_mask')
                                                                            ->label('Customize Mask')
                                                                            ->inline()
                                                                            ->live(),
                                                                        TextInput::make('custom_mask')
                                                                            ->label(false)
                                                                            ->visible(fn($get) => $get('customize_mask')),
                                                                    ]),
                                                                Fieldset::make('Help Text')
                                                                    ->columns(1)
                                                                    ->columnSpan(1)
                                                                    ->schema([
                                                                        Placeholder::make('help_text')
                                                                            ->label("Default")
                                                                            ->content(fn($get) => FormField::find($get('form_field_id'))->help_text ?? 'null'),
                                                                        Toggle::make('customize_help_text')
                                                                            ->label('Customize Help text')
                                                                            ->inline()
                                                                            ->live(),
                                                                        Textarea::make('custom_help_text')
                                                                            ->label(false)
                                                                            ->visible(fn($get) => $get('customize_help_text')),
                                                                    ]),
                                                            ]),
                                                    ]),
                                                Repeater::make('validations')
                                                    ->label('Validations')
                                                    ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->defaultItems(0)
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
                                                    ]),
                                                Repeater::make('conditionals')
                                                    ->label('Conditionals')
                                                    ->itemLabel(fn($state): ?string => $conditionalOptions[$state['type']] ?? 'New Conditional')
                                                    ->collapsible()
                                                    ->collapsed()
                                                    ->defaultItems(0)
                                                    ->addActionAlignment(Alignment::Start)
                                                    ->schema([
                                                        Select::make('type')
                                                            ->label('Conditional Type')
                                                            ->options($conditionalOptions)
                                                            ->reactive()
                                                            ->required(),
                                                        TextInput::make('value')
                                                            ->label('Value'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
                Actions::make([
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = \App\Helpers\FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        })
                        ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                    Action::make('Preview Form Template')
                        ->url(function (Get $get) {
                            $jsonTemplate = $get('generated_text');
                            $encodedJson = base64_encode($jsonTemplate);
                            return route('forms.rendered_forms.preview', ['json' => $encodedJson]);
                        })
                        ->openUrlInNewTab()
                        ->disabled(fn(Get $get) => empty($get('generated_text')))
                        ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                ]),
                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => ! ($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('form.form_id_title')
                    ->label('Form')
                    ->searchable(),
                Tables\Columns\TextColumn::make('version_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_to')
                    ->searchable(),
                Tables\Columns\TextColumn::make('deployed_at')
                    ->date('M j, Y')
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
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => (in_array($record->status, ['draft', 'testing'])) && Gate::allows('form-developer')),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->visible(fn($record) => $record->status === 'published')
                    ->action(function ($record) {
                        $record->update(['status' => 'archived']);
                    })
                    ->requiresConfirmation()
                    ->color('danger')
                    ->tooltip('Archive this form version'),
                Tables\Actions\Action::make('Create New Version')
                    ->label('Create New Version')
                    ->icon('heroicon-o-document-plus')
                    ->requiresConfirmation()
                    ->tooltip('Create a new version from this version')
                    ->visible(fn($record) => (Gate::allows('form-developer') && in_array($record->status, ['published', 'archived'])))
                    ->action(function ($record, $livewire) {
                        $newVersion = $record->replicate();
                        $newVersion->status = 'draft';
                        $newVersion->deployed_to = null;
                        $newVersion->deployed_at = null;
                        $newVersion->save();

                        foreach ($record->formInstanceFields()->whereNull('field_group_instance_id')->get() as $field) {
                            $newField = $field->replicate();
                            $newField->form_version_id = $newVersion->id;
                            $newField->save();

                            foreach ($field->validations as $validation) {
                                $newValidation = $validation->replicate();
                                $newValidation->form_instance_field_id = $newField->id;
                                $newValidation->save();
                            }

                            foreach ($field->conditionals as $conditional) {
                                $newConditional = $conditional->replicate();
                                $newConditional->form_instance_field_id = $newField->id;
                                $newConditional->save();
                            }
                        }

                        foreach ($record->fieldGroupInstances as $groupInstance) {
                            $newGroupInstance = $groupInstance->replicate();
                            $newGroupInstance->form_version_id = $newVersion->id;
                            $newGroupInstance->save();

                            foreach ($groupInstance->formInstanceFields as $field) {
                                $newField = $field->replicate();
                                $newField->form_version_id = $newVersion->id;
                                $newField->field_group_instance_id = $newGroupInstance->id;
                                $newField->save();

                                foreach ($field->validations as $validation) {
                                    $newValidation = $validation->replicate();
                                    $newValidation->form_instance_field_id = $newField->id;
                                    $newValidation->save();
                                }

                                foreach ($field->conditionals as $conditional) {
                                    $newConditional = $conditional->replicate();
                                    $newConditional->form_instance_field_id = $newField->id;
                                    $newConditional->save();
                                }
                            }
                        }
                        $livewire->redirect(FormVersionResource::getUrl('edit', ['record' => $newVersion]));
                    }),
            ])
            ->bulkActions([
                //
            ])
            ->paginated([
                10,
                25,
                50,
                100,
            ]);;
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
            'index' => Pages\ListFormVersions::route('/'),
            'create' => Pages\CreateFormVersion::route('/create'),
            'edit' => Pages\EditFormVersion::route('/{record}/edit'),
            'view' => Pages\ViewFormVersion::route('/{record}'),
        ];
    }
}
