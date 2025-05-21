<?php

namespace App\Filament\Components;

use App\Helpers\FormTemplateHelper;
use App\Helpers\FormDataHelper;
use App\Filament\Components\FormVersionMetadata;
use App\Filament\Components\Modals\FormFieldDetailsModal;
use App\Filament\Components\Modals\FieldGroupDetailsModal;
use App\Filament\Components\Modals\ContainerDetailsModal;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Facades\Session;
use Closure;

class FormVersionBuilder
{
    public static function schema(): Grid
    {
        FormDataHelper::load();

        return Grid::make()
            ->schema([
                ...FormVersionMetadata::schema(),

                Builder::make('components')
                    ->label('Form Elements')
                    ->addActionLabel('Add to Form Elements')
                    ->addBetweenActionLabel('Insert between elements')
                    ->columnSpan(2)
                    ->collapsible(false)
                    ->reorderableWithButtons()
                    ->reorderableWithDragAndDrop(false)
                    ->blockNumbers(false)
                    ->cloneable()
                    ->afterStateHydrated(function (Set $set, Get $get) {
                        Session::put('elementCounter', self::getHighestID($get('components') ?? []) + 1);
                    })
                    ->blocks([
                        self::simplifiedFormFieldBlock(fn() => FormTemplateHelper::calculateElementID()),
                        self::simplifiedFieldGroupBlock(fn() => FormTemplateHelper::calculateElementID()),
                        self::simplifiedContainerBlock(fn() => FormTemplateHelper::calculateElementID()),
                    ]),

                Hidden::make('all_instance_ids')
                    ->default(fn(Get $get) => $get('all_instance_ids') ?? [])
                    ->dehydrated(fn() => true),

                Actions::make([
                    Action::make('Generate Form Template')
                        ->action(function (Get $get, Set $set) {
                            $formId = $get('id');
                            $jsonTemplate = FormTemplateHelper::generateJsonTemplate($formId);
                            $set('generated_text', $jsonTemplate);
                        })
                        ->hidden(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
                ]),

                Textarea::make('generated_text')
                    ->label('Generated Form Template')
                    ->columnSpan(2)
                    ->rows(15)
                    ->hidden(fn($livewire) => !($livewire instanceof \Filament\Resources\Pages\ViewRecord)),
            ]);
    }

    protected static function simplifiedFormFieldBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('form_field')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Form Field';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                $label = 'Unknown Field';
                $dataType = 'unknown';

                if (isset($state['formField']) && isset($state['formField']['label'])) {
                    $label = $state['formField']['label'];

                    if (isset($state['formField']['data_type']) && isset($state['formField']['data_type']['name'])) {
                        $dataType = $state['formField']['data_type']['name'];
                    }
                } else {
                    $formFields = FormDataHelper::get('form_fields');
                    $fieldId = $state['form_field_id'] ?? null;
                    $field = $formFields->firstWhere('id', $fieldId);

                    if ($field) {
                        $label = $field->label;
                        $dataType = $field->dataType->name ?? 'unknown';
                    }
                }

                if (!empty($state['customize_label']) && $state['customize_label'] === 'customize' && !empty($state['custom_label'])) {
                    $label = $state['custom_label'];
                }

                return "$label | $dataType | ID: $id";
            })
            ->icon('heroicon-o-document-text')
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                Select::make('form_field_id')
                    ->label('Field')
                    ->options(function () {
                        $formFields = FormDataHelper::get('form_fields');
                        return $formFields->pluck('label', 'id')->toArray();
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        $formFields = FormDataHelper::get('form_fields');
                        return $formFields
                            ->filter(function ($field) use ($search) {
                                return stripos($field->label, $search) !== false;
                            })
                            ->pluck('label', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('custom_label')
                    ->default(''),

                Hidden::make('customize_label')
                    ->default(''),

                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->modalHeading('Form Field Details')
                        ->modalIcon('heroicon-o-document-text')
                        ->modalSubmitActionLabel('Save Form Field Details')
                        ->fillForm(function (Get $get) {
                            return [
                                'instance_id' => $get('instance_id'),
                                'form_field_id' => $get('form_field_id'),
                                'custom_label' => $get('custom_label'),
                                'customize_label' => !empty($get('custom_label')),
                                'custom_help_text' => $get('custom_help_text'),
                                'customize_help_text' => !empty($get('custom_help_text')),
                                'custom_data_binding' => $get('custom_data_binding'),
                                'customize_data_binding' => !empty($get('custom_data_binding')),
                                'custom_data_binding_path' => $get('custom_data_binding_path'),
                                'customize_data_binding_path' => !empty($get('custom_data_binding_path')),
                            ];
                        })
                        ->form(fn() => FormFieldDetailsModal::getSchema())
                        ->action(function (array $data, Get $get, Set $set) {
                            if ($data['form_field_id'] !== $get('form_field_id')) {
                                $set('form_field_id', $data['form_field_id']);
                            }

                            if ($data['customize_label']) {
                                $set('custom_label', $data['custom_label']);
                            } else {
                                $set('custom_label', '');
                            }

                            if ($data['customize_help_text']) {
                                $set('custom_help_text', $data['custom_help_text']);
                            } else {
                                $set('custom_help_text', '');
                            }

                            if ($data['customize_data_binding']) {
                                $set('custom_data_binding', $data['custom_data_binding']);
                            } else {
                                $set('custom_data_binding', '');
                            }

                            if ($data['customize_data_binding_path']) {
                                $set('custom_data_binding_path', $data['custom_data_binding_path']);
                            } else {
                                $set('custom_data_binding_path', '');
                            }
                        }),
                ]),
            ]);
    }

    protected static function simplifiedFieldGroupBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('field_group')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Field Group';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                $label = 'Unknown Group';

                if (isset($state['fieldGroup']) && isset($state['fieldGroup']['label'])) {
                    $label = $state['fieldGroup']['label'];
                } else {
                    $fieldGroups = FormDataHelper::get('field_groups');
                    $groupId = $state['field_group_id'] ?? null;
                    $group = $fieldGroups->firstWhere('id', $groupId);

                    if ($group) {
                        $label = $group->label;
                    }
                }

                if (!empty($state['customize_group_label']) && $state['customize_group_label'] === 'customize' && !empty($state['custom_group_label'])) {
                    $label = $state['custom_group_label'];
                }

                return "$label | group | ID: $id";
            })
            ->icon('heroicon-o-table-cells')
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                Select::make('field_group_id')
                    ->label('Field Group')
                    ->options(function () {
                        $fieldGroups = FormDataHelper::get('field_groups');
                        return $fieldGroups->pluck('label', 'id')->toArray();
                    })
                    ->getSearchResultsUsing(function (string $search) {
                        $fieldGroups = FormDataHelper::get('field_groups');

                        return $fieldGroups
                            ->filter(function ($group) use ($search) {
                                return stripos($group->label, $search) !== false;
                            })
                            ->pluck('label', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('custom_group_label')
                    ->default(''),

                Hidden::make('customize_group_label')
                    ->default(''),

                Hidden::make('form_fields')
                    ->default([]),

                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->modalHeading('Field Group Details')
                        ->modalIcon('heroicon-o-table-cells')
                        ->modalSubmitActionLabel('Save Field Group Details')
                        ->mountUsing(function (array $data, Get $get) {
                            return [
                                'instance_id' => $get('instance_id'),
                                'field_group_id' => $get('field_group_id'),
                            ];
                        })
                        ->form(fn() => FieldGroupDetailsModal::getSchema())
                        ->action(function (array $data, Get $get, Set $set) {
                            $set('field_group_id', $data['field_group_id']);
                        }),
                ]),
            ]);
    }

    protected static function simplifiedContainerBlock(Closure $calculateIDCallback): Block
    {
        return Block::make('container')
            ->label(function (?array $state): string {
                if ($state === null) {
                    return 'Container';
                }

                $id = !empty($state['customize_instance_id']) && !empty($state['custom_instance_id'])
                    ? $state['custom_instance_id']
                    : $state['instance_id'] ?? 'unknown';

                return "Container | ID: $id";
            })
            ->schema([
                Hidden::make('instance_id')
                    ->default($calculateIDCallback),

                Hidden::make('customize_instance_id')
                    ->default(fn(Get $get) => !empty($get('custom_instance_id'))),

                Hidden::make('components')
                    ->default([]),

                Actions::make([
                    Action::make('details')
                        ->label('Details')
                        ->icon('heroicon-o-information-circle')
                        ->button()
                        ->modalHeading('Container Details')
                        ->modalIcon('heroicon-o-cube')
                        ->modalSubmitActionLabel('Close')
                        ->mountUsing(function (array $data, Get $get) {
                            return [
                                'instance_id' => $get('instance_id'),
                            ];
                        })
                        ->form(fn() => ContainerDetailsModal::getSchema()),
                ]),
            ]);
    }

    protected static function getHighestID(array $blocks): int
    {
        $maxID = 0;

        foreach ($blocks as $block) {
            if (!is_array($block) || !isset($block['data'])) {
                continue;
            }

            if (isset($block['data']['instance_id'])) {
                $idString = $block['data']['instance_id'];
                if (is_string($idString)) {
                    $numericPart = str_replace('element', '', $idString);
                    if (is_numeric($numericPart) && $numericPart > 0) {
                        $id = (int) $numericPart;
                        $maxID = max($maxID, $id);
                    }
                }
            }

            if (isset($block['data']['components']) && is_array($block['data']['components'])) {
                $nestedMaxID = self::getHighestID($block['data']['components']);
                $maxID = max($maxID, $nestedMaxID);
            }

            if (isset($block['data']['form_fields']) && is_array($block['data']['form_fields'])) {
                $nestedMaxID = self::getHighestID($block['data']['form_fields']);
                $maxID = max($maxID, $nestedMaxID);
            }
        }

        return $maxID;
    }
}
