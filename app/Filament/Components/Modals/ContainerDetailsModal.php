<?php

namespace App\Filament\Components\Modals;

use App\Models\Container;
use App\Models\FormInstanceField;
use App\Models\FieldGroupInstance;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

class ContainerDetailsModal
{
    public static function form(array $state, bool $viewMode = false): array
    {
        $formFields = [];
        $fieldGroups = [];
        $webStyles = [];
        $pdfStyles = [];

        if (!empty($state['id'])) {
            $container = Container::with([
                'formInstanceFields' => function ($query) {
                    $query->whereNull('field_group_instance_id')
                        ->with(['formField:id,label,data_type_id', 'formField.dataType:id,name']);
                },
                'fieldGroupInstances' => function ($query) {
                    $query->with([
                        'fieldGroup:id,label',
                        'formInstanceFields' => function ($query) {
                            $query->with(['formField:id,label,data_type_id', 'formField.dataType:id,name']);
                        }
                    ]);
                },
                'styleInstances.style'
            ])->find($state['id']);

            if ($container) {
                $formFields = $container->formInstanceFields->map(function ($field) {
                    return [
                        'id' => $field->id,
                        'instance_id' => $field->instance_id,
                        'label' => $field->formField ? $field->formField->label : 'Unknown field',
                        'type' => $field->formField && $field->formField->dataType ? $field->formField->dataType->name : 'Unknown type',
                        'form_field_id' => $field->form_field_id,
                        'element_type' => 'form_field',
                    ];
                })->toArray();

                $fieldGroups = $container->fieldGroupInstances->map(function ($group) {
                    $fieldCount = $group->formInstanceFields->count();
                    return [
                        'id' => $group->id,
                        'instance_id' => $group->instance_id,
                        'label' => $group->fieldGroup ? $group->fieldGroup->label : 'Unknown group',
                        'field_count' => $fieldCount,
                        'field_group_id' => $group->field_group_id,
                        'element_type' => 'field_group',
                    ];
                })->toArray();

                foreach ($container->styleInstances as $styleInstance) {
                    if ($styleInstance->type === 'web') {
                        $webStyles[] = $styleInstance->style_id;
                    } elseif ($styleInstance->type === 'pdf') {
                        $pdfStyles[] = $styleInstance->style_id;
                    }
                }
            }
        }

        $allElements = array_merge($formFields, $fieldGroups);

        usort($allElements, function ($a, $b) {
            return strcmp($a['element_type'], $b['element_type']);
        });

        return [
            Tabs::make('Container Details')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Basic Information')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('ID')
                                        ->schema([
                                            Placeholder::make('instance_id')
                                                ->label('Default Instance ID')
                                                ->content(function () use ($state) {
                                                    return $state['instance_id'] ?? 'Unknown';
                                                }),

                                            TextInput::make('custom_instance_id')
                                                ->label('Custom Instance ID')
                                                ->helperText('Leave empty to use default instance ID')
                                                ->default($state['custom_instance_id'] ?? null)
                                                ->disabled($viewMode),
                                        ]),

                                    Fieldset::make('Visibility')
                                        ->schema([
                                            Textarea::make('visibility')
                                                ->label('Visibility Condition')
                                                ->helperText('JavaScript expression to determine visibility')
                                                ->default($state['visibility'] ?? null)
                                                ->columnSpanFull()
                                                ->disabled($viewMode),

                                            \Filament\Forms\Components\Toggle::make('clear_button')
                                                ->label('Show Clear Button')
                                                ->default($state['clear_button'] ?? false)
                                                ->disabled($viewMode),
                                        ])
                                        ->columns(1),
                                ]),
                        ]),

                    Tab::make('Styles')
                        ->icon('heroicon-o-paint-brush')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    Fieldset::make('Web Styles')
                                        ->schema([
                                            Select::make('web_styles')
                                                ->label('Custom Web Styles')
                                                ->options(function () {
                                                    $styles = \App\Helpers\FormDataHelper::get('styles')
                                                        ->pluck('name', 'id');
                                                    return $styles;
                                                })
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->placeholder('Select custom web styles')
                                                ->default($webStyles)
                                                ->helperText('Select styles for web display')
                                                ->disabled($viewMode),
                                        ]),

                                    Fieldset::make('PDF Styles')
                                        ->schema([
                                            Select::make('pdf_styles')
                                                ->label('Custom PDF Styles')
                                                ->options(function () {
                                                    $styles = \App\Helpers\FormDataHelper::get('styles')
                                                        ->pluck('name', 'id');
                                                    return $styles;
                                                })
                                                ->multiple()
                                                ->searchable()
                                                ->preload()
                                                ->placeholder('Select custom PDF styles')
                                                ->default($pdfStyles)
                                                ->helperText('Select styles for PDF display')
                                                ->disabled($viewMode),
                                        ]),
                                ]),
                        ]),

                    Tab::make('Elements')
                        ->icon('heroicon-o-rectangle-stack')
                        ->schema([
                            Placeholder::make('elements_count')
                                ->label('Number of Elements')
                                ->content(count($allElements) . ' element(s): ' . count($formFields) . ' form field(s), ' . count($fieldGroups) . ' field group(s)')
                                ->columnSpanFull(),

                            Placeholder::make('no_elements')
                                ->label('No Elements')
                                ->content('This container has no elements.')
                                ->visible(fn() => count($allElements) === 0)
                                ->columnSpanFull(),

                            \Filament\Forms\Components\Repeater::make('elements')
                                ->label('')
                                ->schema([
                                    TextInput::make('instance_id')
                                        ->label('Instance ID')
                                        ->disabled(),
                                    TextInput::make('label')
                                        ->label('Label')
                                        ->disabled(),
                                    TextInput::make('element_type')
                                        ->label('Type')
                                        ->disabled(),
                                    TextInput::make('field_count')
                                        ->label('Fields')
                                        ->disabled()
                                        ->visible(fn($state): bool => is_array($state) && ($state['element_type'] ?? '') === 'field_group'),
                                    Actions::make([
                                        Action::make('edit_element')
                                            ->label('Edit')
                                            ->icon('heroicon-o-pencil-square')
                                            ->button()
                                            ->size('sm')
                                            ->modalHeading(fn(array $state): string => $state['element_type'] === 'form_field' ? 'Form Field Details' : 'Field Group Details')
                                            ->modalIcon(fn(array $state): string => $state['element_type'] === 'form_field' ? 'heroicon-o-document-text' : 'heroicon-o-table-cells')
                                            ->modalWidth(\Filament\Support\Enums\MaxWidth::FiveExtraLarge)
                                            ->modalSubmitActionLabel(fn(array $state) => $viewMode
                                                ? 'Close'
                                                : ($state['element_type'] === 'form_field' ? 'Save Form Field Details' : 'Save Field Group Details'))
                                            ->form(function (array $state) use ($viewMode) {
                                                if ($state['element_type'] === 'form_field') {
                                                    return FormFieldDetailsModal::form([
                                                        'id' => $state['id'],
                                                        'instance_id' => $state['instance_id'],
                                                        'form_field_id' => $state['form_field_id'],
                                                    ], $viewMode);
                                                } else {
                                                    return FieldGroupDetailsModal::form([
                                                        'id' => $state['id'],
                                                        'instance_id' => $state['instance_id'],
                                                        'field_group_id' => $state['field_group_id'],
                                                    ], $viewMode);
                                                }
                                            })
                                            ->action(function (array $data, $livewire) use ($state, $viewMode) {
                                                if (!$viewMode) {
                                                    if ($state['element_type'] === 'form_field') {
                                                        FormFieldDetailsModal::action($data);
                                                    } else {
                                                        FieldGroupDetailsModal::action($data);
                                                    }
                                                }
                                            }),
                                    ])
                                ])
                                ->columns(5)
                                ->default($allElements)
                                ->visible(fn() => count($allElements) > 0)
                                ->columnSpanFull()
                                ->disabled($viewMode),

                            Actions::make([
                                Action::make('add_form_field')
                                    ->label('Add Form Field')
                                    ->icon('heroicon-o-document-plus')
                                    ->button()
                                    ->modalHeading('Add Form Field')
                                    ->modalWidth('lg')
                                    ->form([
                                        Select::make('form_field_id')
                                            ->label('Form Field')
                                            ->options(function () {
                                                $fields = \App\Helpers\FormDataHelper::get('form_fields');
                                                return $fields->mapWithKeys(fn($field) => [
                                                    $field->id => "{$field->label} | {$field->dataType?->name}"
                                                ]);
                                            })
                                            ->searchable()
                                            ->required()
                                            ->preload(),
                                    ])
                                    ->action(function (array $data, Get $get, Set $set, $livewire) {
                                        $containerId = $get('id');
                                        if (!$containerId) {
                                            return;
                                        }

                                        $container = Container::find($containerId);
                                        if (!$container) {
                                            return;
                                        }

                                        $formField = \App\Models\FormField::find($data['form_field_id']);
                                        if (!$formField) {
                                            return;
                                        }

                                        $instanceId = 'field_' . uniqid();

                                        $formInstanceField = \App\Models\FormInstanceField::create([
                                            'form_version_id' => $container->form_version_id,
                                            'form_field_id' => $data['form_field_id'],
                                            'container_id' => $containerId,
                                            'instance_id' => $instanceId,
                                            'order' => 999,
                                        ]);

                                        Notification::make()
                                            ->title('Form field added')
                                            ->success()
                                            ->send();

                                        $livewire->dispatch('refresh-form');
                                    }),

                                Action::make('add_field_group')
                                    ->label('Add Field Group')
                                    ->icon('heroicon-o-table-cells')
                                    ->button()
                                    ->modalHeading('Add Field Group')
                                    ->modalWidth('lg')
                                    ->form([
                                        Select::make('field_group_id')
                                            ->label('Field Group')
                                            ->options(function () {
                                                $fieldGroups = \App\Helpers\FormDataHelper::get('field_groups');
                                                return $fieldGroups->mapWithKeys(fn($group) => [
                                                    $group->id => "{$group->label}"
                                                ]);
                                            })
                                            ->searchable()
                                            ->required()
                                            ->preload(),
                                    ])
                                    ->action(function (array $data, Get $get, Set $set, $livewire) {
                                        $containerId = $get('id');
                                        if (!$containerId) {
                                            return;
                                        }

                                        $container = Container::find($containerId);
                                        if (!$container) {
                                            return;
                                        }

                                        $fieldGroup = \App\Models\FieldGroup::find($data['field_group_id']);
                                        if (!$fieldGroup) {
                                            return;
                                        }

                                        $instanceId = 'group_' . uniqid();

                                        $fieldGroupInstance = \App\Models\FieldGroupInstance::create([
                                            'form_version_id' => $container->form_version_id,
                                            'field_group_id' => $data['field_group_id'],
                                            'container_id' => $containerId,
                                            'instance_id' => $instanceId,
                                            'order' => 999,
                                        ]);

                                        Notification::make()
                                            ->title('Field group added')
                                            ->success()
                                            ->send();

                                        $livewire->dispatch('refresh-form');
                                    }),
                            ])
                                ->columnSpanFull()
                                ->visible(!$viewMode),
                        ]),
                ]),

            Hidden::make('id')
                ->default($state['id'] ?? null),
        ];
    }

    public static function action(array $data): void
    {
        $containerId = $data['id'] ?? null;
        if (!$containerId) {
            return;
        }

        $container = Container::where('id', $containerId)->first();
        if (!$container) {
            return;
        }

        $container->custom_instance_id = !empty($data['custom_instance_id']) ? $data['custom_instance_id'] : null;
        $container->visibility = !empty($data['visibility']) ? $data['visibility'] : null;
        $container->clear_button = $data['clear_button'] ?? false;

        $container->save();

        if (isset($data['web_styles'])) {
            $container->styleInstances()->where('type', 'web')->delete();

            foreach ($data['web_styles'] as $styleId) {
                $container->styleInstances()->create([
                    'style_id' => $styleId,
                    'type' => 'web',
                ]);
            }
        }

        if (isset($data['pdf_styles'])) {
            $container->styleInstances()->where('type', 'pdf')->delete();

            foreach ($data['pdf_styles'] as $styleId) {
                $container->styleInstances()->create([
                    'style_id' => $styleId,
                    'type' => 'pdf',
                ]);
            }
        }

        Notification::make()
            ->title('Container updated')
            ->success()
            ->send();
    }
}
