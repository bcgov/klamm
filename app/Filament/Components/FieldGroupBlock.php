<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Models\FieldGroup;
use App\Models\FormDataSource;
use App\Models\Style;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

class FieldGroupBlock
{
    public static function make(): Block
    {
        return Block::make('field_group')
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
                                $styles = $field->styles()->pluck('styles.id')->toArray();
                                $validations = $field->validations()->get()->map(function ($validation) {
                                    return [
                                        'type' => $validation->type,
                                        'value' => $validation->value,
                                        'error_message' => $validation->error_message,
                                    ];
                                })->toArray();
                                return [
                                    'type' => 'form_field',
                                    'data' => [
                                        'form_field_id' => $field->id,
                                        'label' => $field->label,
                                        'data_binding_path' => $field->data_binding_path,
                                        'data_binding' => $field->data_binding,
                                        'help_text' => $field->help_text,
                                        'styles' => $styles,
                                        'mask' => $field->mask,
                                        'validations' => $validations,
                                        'conditionals' => [],
                                        'instance_id' => 'nestedField' . $index + 1,
                                        'customize_label' => 'default',
                                        'customize_group_label' => 'default',
                                    ],
                                ];
                            })->toArray();
                            $set('form_fields', $formFields);
                            $set('styles', $fieldGroup->styles()->pluck('styles.id')->toArray());
                        } else {
                            $set('form_fields', []);
                            $set('styles', []);
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
                                            ->default(fn($get) => FormTemplateHelper::calculateFieldID($get('../../'))), // Set the sequential default value
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
                Select::make('styles')
                    ->options(Style::pluck('name', 'id'))
                    ->multiple()
                    ->preload()
                    ->columnSpan(2)
                    ->live()
                    ->reactive(),
                Builder::make('form_fields')
                    ->label('Form Fields in Group')
                    ->addBetweenActionLabel('Insert between fields')
                    ->collapsible()
                    ->collapsed(true)
                    ->blockNumbers(false)
                    ->columnSpan(2)
                    ->cloneable()
                    ->blocks([
                        FormFieldBlock::make(fn($get) => FormTemplateHelper::calculateFieldInGroupID($get('../../'))),
                    ]),
            ]);
    }
}
