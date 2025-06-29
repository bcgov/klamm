<?php

namespace App\Filament\Components;

use App\Helpers\DateFormatHelper;
use App\Helpers\UniqueIDsHelper;
use App\Helpers\FormDataHelper;
use Closure;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Actions\Action;

class FormFieldBlock
{
    public static function make(Closure $calculateIDCallback): Block
    {
        $fields = FormDataHelper::get('fields');
        $dataSources = FormDataHelper::get('dataSources');
        $selectOptions = FormDataHelper::get('selectOptions');
        $styles = FormDataHelper::get('styles');

        $isDate = fn($get) => $fields->get($get('form_field_id'))?->dataType->name === 'date';

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

        return Block::make('form_field')
            ->label(function (?array $state) use ($fields): string {
                if ($state === null) {
                    return 'Field';
                }
                $field = $fields->get($state['form_field_id']);
                if ($field) {
                    $label = '';
                    if ($state['customize_label'] !== 'hide') {
                        $customLabel = strlen($state['custom_label'] ?? '') > 50 ? substr($state['custom_label'] ?? null, 0, 50) . ' ...' : $state['custom_label'] ?? null;
                        $label .= ($customLabel ?? $field->label ?? 'null') . ' | ';
                    } else {
                        $label .= '(label hidden) | ';
                    }
                    $label .= ($field->dataType->name ?? '') . ' | id: ';
                    if (!empty($state['customize_instance_id'] ?? null) && !empty($state['custom_instance_id'] ?? null)) {
                        $label .= $state['custom_instance_id'];
                    } else {
                        $label .= $state['instance_id'] ?? 'null';
                    }
                    return $label;
                }
                return 'New Field | id: ' . $state['instance_id'];
            })
            ->icon('icon-text-cursor-input')
            ->columns(2)
            ->preview('filament.forms.resources.form-resource.components.block-previews.blank')
            ->schema([
                Select::make('form_field_id')
                    ->label('Form Field')
                    ->live()
                    ->options(fn() => $fields->mapWithKeys(fn($field) => [
                        $field->id => "{$field->label} | {$field->dataType?->name} | name: {$field->name}"
                    ]))
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->columnSpan(2)
                    ->afterStateUpdated(function ($state, callable $set) use ($fields) {
                        $field = $fields->get($state);
                        if ($field) {
                            $set('webStyles', $field->webStyles->pluck('id')->toArray());
                            $set('pdfStyles', $field->pdfStyles->pluck('id')->toArray());
                            $set('validations', $field->validations->map(fn($validation) => [
                                'type' => $validation->type,
                                'value' => $validation->value,
                                'error_message' => $validation->error_message,
                            ])->toArray());
                            $set('select_option_instances', $field->selectOptionInstances->map(fn($instance) => [
                                'type' => 'select_option_instance',
                                'data' => [
                                    'select_option_id' => $instance->select_option_id,
                                    'order' => $instance->order,
                                ],
                            ])->toArray());
                        } else {
                            // Reset when no field is selected
                            $set('webStyles', []);
                            $set('pdfStyles', []);
                            $set('validations', []);
                            $set('select_option_instances', []);
                        }
                    }),
                Section::make('Field Properties')
                    ->collapsible()
                    ->collapsed(true)
                    ->compact()
                    ->columnSpan(2)
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Fieldset::make('Instance ID')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('instance_id_placeholder') // used to view value in builder
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $get('instance_id')), // Set the sequential default value
                                        Hidden::make('instance_id') // used to populate value in template
                                            ->hidden()
                                            ->default($calculateIDCallback), // Set the sequential default value
                                        Toggle::make('customize_instance_id')
                                            ->label('Customize Instance ID')
                                            ->inline()
                                            ->lazy(),
                                        TextInput::make('custom_instance_id')
                                            ->label(false)
                                            ->alphanum()
                                            ->lazy()
                                            ->distinct()
                                            ->alphaNum()
                                            ->rule(fn() => UniqueIDsHelper::uniqueIDsRule())
                                            ->visible(fn($get) => $get('customize_instance_id')),
                                    ]),
                                Fieldset::make('Label')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('label')
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $fields->get($get('form_field_id'))->label ?? 'null'),
                                        Radio::make('customize_label')
                                            ->options([
                                                'default' => 'Use Default',
                                                'hide' => 'Hide Label',
                                                'customize' => 'Customize Label'
                                            ])
                                            ->default('default')
                                            ->inline()
                                            ->inlineLabel(false)
                                            ->label(false)
                                            ->live()
                                            ->afterStateUpdated(function ($state, callable $set) {
                                                if ($state !== 'customize') {
                                                    $set('custom_label', null);
                                                }
                                            }),
                                        TextInput::make('custom_label')
                                            ->label(false)
                                            ->lazy()
                                            ->visible(fn($get) => $get('customize_label') == 'customize'),
                                    ]),
                                Fieldset::make('Field Value')
                                    ->visible(fn($get) => $fields->get($get('form_field_id'))?->isValueInputNeededForField() ?? false)
                                    ->columns(1)
                                    ->columnSpanFull()
                                    ->schema([
                                        RichEditor::make('field_value')
                                            ->label("Default")
                                            ->toolbarButtons([])
                                            ->disabled()
                                            ->afterStateHydrated(function ($state, callable $set, callable $get) use ($fields) {
                                                $value = $fields->get($get('form_field_id'))->formFieldValue?->value ?? '';
                                                $set('field_value', $value);
                                            }),
                                        Toggle::make('customize_field_value')
                                            ->label('Customize Field Value')
                                            ->inline()
                                            ->lazy(),
                                        RichEditor::make('custom_field_value')
                                            ->label(false)
                                            ->visible(fn($get) => $get('customize_field_value'))
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
                                            ]),
                                    ]),
                                Fieldset::make('Data Bindings')
                                    ->columns(6)
                                    ->schema([
                                        Fieldset::make('Data Source')
                                            ->columns(1)
                                            ->columnSpan(fn($get) => $isDate($get) ? 2 : 3)
                                            ->schema([
                                                Placeholder::make('data_binding')
                                                    ->label("Default")
                                                    ->dehydrated(false)
                                                    ->content(fn($get) => $fields->get($get('form_field_id'))->data_binding ?? 'null'),
                                                Toggle::make('customize_data_binding')
                                                    ->label('Customize Data Source')
                                                    ->inline()
                                                    ->lazy(),
                                                Select::make('custom_data_binding')
                                                    ->label(false)
                                                    ->options($dataSources->pluck('name', 'name'))
                                                    ->visible(fn($get) => $get('customize_data_binding')),
                                            ]),
                                        Fieldset::make('Data Binding Path')
                                            ->columns(1)
                                            ->columnSpan(fn($get) => $isDate($get) ? 2 : 3)
                                            ->schema([
                                                Placeholder::make('data_binding_path')
                                                    ->label("Default")
                                                    ->dehydrated(false)
                                                    ->content(fn($get) => $fields->get($get('form_field_id'))->data_binding_path ?? 'null'),
                                                Toggle::make('customize_data_binding_path')
                                                    ->label('Customize Data Binding Path')
                                                    ->inline()
                                                    ->lazy(),
                                                Textarea::make('custom_data_binding_path')
                                                    ->label(false)
                                                    ->rows(1)
                                                    ->visible(fn($get) => $get('customize_data_binding_path')),
                                            ]),
                                        Fieldset::make('Date Format')
                                            ->columns(1)
                                            ->columnSpan(2)
                                            ->visible($isDate)
                                            ->schema([
                                                Placeholder::make('date_format')
                                                    ->label("Default")
                                                    ->dehydrated(false)
                                                    ->content(fn($get) => $fields->get($get('form_field_id'))->formFieldDateFormat?->date_format ?? 'null'),
                                                Toggle::make('customize_date_format')
                                                    ->label('Customize Data Source')
                                                    ->inline()
                                                    ->lazy(),
                                                Select::make('custom_date_format')
                                                    ->label(false)
                                                    ->options(DateFormatHelper::dateFormats())
                                                    ->visible(fn($get) => $get('customize_date_format')),
                                            ]),
                                    ]),
                                Fieldset::make('Mask')
                                    ->columns(1)
                                    ->columnSpan(1)
                                    ->schema([
                                        Placeholder::make('mask')
                                            ->label("Default")
                                            ->dehydrated(false)
                                            ->content(fn($get) => $fields->get($get('form_field_id'))->mask ?? 'null'),
                                        Toggle::make('customize_mask')
                                            ->label('Customize Mask')
                                            ->inline()
                                            ->lazy(),
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
                                            ->dehydrated(false)
                                            ->content(fn($get) => $fields->get($get('form_field_id'))->help_text ?? 'null'),
                                        Toggle::make('customize_help_text')
                                            ->label('Customize Help text')
                                            ->inline()
                                            ->lazy(),
                                        Textarea::make('custom_help_text')
                                            ->label(false)
                                            ->rows(1)
                                            ->visible(fn($get) => $get('customize_help_text')),
                                    ]),
                            ]),
                    ]),
                Builder::make('select_option_instances')
                    ->label('Select Option Instances')
                    ->columnSpanFull()
                    ->reorderable()
                    ->blockNumbers(false)
                    ->collapsible()
                    ->collapsed(true)
                    ->live()
                    ->reactive()
                    ->visible(fn($get) => in_array($fields->get($get('form_field_id'))?->dataType?->name, ['radio', 'dropdown']))
                    ->blocks([
                        Block::make('select_option_instance')
                            ->preview('filament.forms.resources.form-resource.components.block-previews.blank')
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
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->options($selectOptions->map(function ($option) {
                                        return "{$option->label} | {$option->name} | value: {$option->value}";
                                    })->toArray()),
                            ])
                    ]),

                Select::make('webStyles')
                    ->label('Web Styles')
                    ->options($styles->pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
                Select::make('pdfStyles')
                    ->label('PDF Styles')
                    ->options($styles->pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(1),
                Section::make('Validations & Conditionals')
                    ->collapsible()
                    ->collapsed(true)
                    ->compact()
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        Repeater::make('validations')
                            ->label('Validations')
                            ->itemLabel(fn($state): ?string => $validationOptions[$state['type']] ?? 'New Validation')
                            ->collapsible()
                            ->collapsed()
                            ->defaultItems(0)
                            ->columns(1)
                            ->columnSpan(1)
                            ->addActionAlignment(Alignment::Start)
                            ->schema([
                                Select::make('type')
                                    ->label('Validation Type')
                                    ->options($validationOptions)
                                    ->reactive()
                                    ->required(),
                                Textarea::make('value')
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
                            ->columns(1)
                            ->columnSpan(1)
                            ->addActionAlignment(Alignment::Start)
                            ->schema([
                                Select::make('type')
                                    ->label('Conditional Type')
                                    ->options($conditionalOptions)
                                    ->reactive()
                                    ->required(),
                                Textarea::make('value')
                                    ->label('Value'),
                            ]),
                    ])
            ]);
    }
}
