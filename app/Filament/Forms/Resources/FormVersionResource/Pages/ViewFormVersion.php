<?php

namespace App\Filament\Forms\Resources\FormVersionResource\Pages;

use App\Filament\Forms\Resources\FormVersionResource;
use App\Models\FormApprovalRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Filament\Actions;

class ViewFormVersion extends ViewRecord
{
    protected static string $resource = FormVersionResource::class;

    public array $additionalApprovers = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make('view')
                ->url(fn($record) => route('filament.forms.resources.forms.view', ['record' => $record->form_id]))
                ->icon('heroicon-o-eye')
                ->label('View Form Metadata'),
            Actions\EditAction::make()
                ->visible(fn() => $this->record->status === 'draft'),
            Action::make('readyForReview')
                ->label('Ready for Review')
                ->modalHeading('Request approval')
                ->modalDescription(fn() => 'Form: ' . $this->record->form->form_title)
                ->form([
                    Textarea::make('note')
                        ->label('Note for approver')
                        ->required(),
                    Radio::make('approver')
                        ->label('Select approver')
                        ->options(function () {
                            $businessAreaUsers = $this->record->form->businessAreas->flatMap->users
                                ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                ->toArray();

                            $allOptions = $businessAreaUsers;
                            foreach ($this->additionalApprovers as $key => $value) {
                                $allOptions[$key] = $value;
                            }

                            return $allOptions;
                        })
                        ->required(),
                ])
                ->action(function (array $data): void {
                    if (is_numeric($data['approver'])) {

                        $user = User::find($data['approver']);

                        if (!$user) {
                            Notification::make()
                                ->title('Error: User not found')
                                ->danger()
                                ->send();
                            return;
                        }

                        $approvalRequestData = [
                            'form_version_id' => $this->record->id,
                            'approver_id' => $user->id,
                            'approver_name' => $user->name,
                            'approver_email' => $user->email,
                            'note' => $data['note'],
                            'is_klamm_user' => true,
                            'status' => 'pending',
                        ];


                        FormApprovalRequest::create($approvalRequestData);
                    } else {

                        $approverData = explode('|', $data['approver']);

                        if (count($approverData) !== 2) {
                            Notification::make()
                                ->title('Error: Invalid approver data')
                                ->danger()
                                ->send();
                            return;
                        }

                        $approvalRequestData = [
                            'form_version_id' => $this->record->id,
                            'approver_name' => $approverData[0],
                            'approver_email' => $approverData[1],
                            'note' => $data['note'],
                            'is_klamm_user' => false,
                            'status' => 'pending',
                            'token' => Str::uuid(),
                        ];

                        FormApprovalRequest::create($approvalRequestData);
                    }

                    $this->record->update(['status' => 'under_review']);

                    Notification::make()
                        ->title('Approval request sent successfully')
                        ->success()
                        ->send();
                })
                ->slideOver()
                ->closeModalByClickingAway(false)
                ->extraModalFooterActions([
                    Action::make('addNewApprover')
                        ->label('Add new approver')
                        ->modalWidth('lg')
                        ->modalHeading('Add a new approver')
                        ->form([
                            Radio::make('approver_type')
                                ->label('Approver Type')
                                ->options([
                                    'klamm' => 'Klamm user',
                                    'non_klamm' => 'Non Klamm user',
                                ])
                                ->descriptions([
                                    'klamm' => 'Best for approvers who do a lot of reviews and want to stay updated on their form status',
                                    'non_klamm' => 'Best for occasional approvers. They\'ll receive a one-time approval link and access Klamm with their IDIR credentials',
                                ])
                                ->required()
                                ->live(),
                            Select::make('klamm_user')
                                ->searchable()
                                ->label('Select User')
                                ->options(function () {
                                    $businessAreaUserIds = $this->record->form->businessAreas->flatMap->users->pluck('id')->toArray();
                                    $additionalKlammUserIds = collect($this->additionalApprovers)
                                        ->keys()
                                        ->filter(fn($key) => is_numeric($key))
                                        ->toArray();

                                    $excludedIds = array_merge($businessAreaUserIds, $additionalKlammUserIds);

                                    return User::whereNotIn('id', $excludedIds)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                        ->toArray();
                                })
                                ->getSearchResultsUsing(function (string $search) {
                                    $businessAreaUserIds = $this->record->form->businessAreas->flatMap->users->pluck('id')->toArray();
                                    $additionalKlammUserIds = collect($this->additionalApprovers)
                                        ->keys()
                                        ->filter(fn($key) => is_numeric($key))
                                        ->toArray();

                                    $excludedIds = array_merge($businessAreaUserIds, $additionalKlammUserIds);

                                    return User::where(function ($query) use ($search) {
                                        $query->where('name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%");
                                    })
                                        ->whereNotIn('id', $excludedIds)
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => $user->name . ' (' . $user->email . ')'])
                                        ->toArray();
                                })
                                ->required()
                                ->visible(fn(Get $get) => $get('approver_type') === 'klamm'),
                            TextInput::make('name')
                                ->required()
                                ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm'),
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->visible(fn(Get $get) => $get('approver_type') === 'non_klamm'),
                        ])
                        ->action(function (array $data): void {
                            if ($data['approver_type'] === 'klamm') {
                                $user = User::find($data['klamm_user']);
                                if ($user) {
                                    $this->additionalApprovers[$user->id] = $user->name . ' (' . $user->email . ')';
                                }
                            } else {
                                $key = $data['name'] . '|' . $data['email'];
                                $this->additionalApprovers[$key] = $data['name'] . ' (' . $data['email'] . ')';
                            }
                        })
                        ->slideOver()
                        ->closeModalByClickingAway(false),
                ])
                ->visible(fn() => $this->record->status === 'draft'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Eager load required records
        $this->record->load([
            'formInstanceFields' => function ($query) {
                $query->whereNull('field_group_instance_id')->whereNull('container_id');
                $query->with([
                    'formField' => function ($query) {
                        $query->with([
                            'dataType',
                            'formFieldValue',
                            'formFieldDateFormat',
                        ]);
                    },
                    'selectOptionInstances',
                    'validations',
                    'conditionals',
                    'styleInstances',
                    'formInstanceFieldValue',
                    'formInstanceFieldDateFormat',
                ]);
            },
            'fieldGroupInstances' => function ($query) {
                $query
                    ->whereNull('container_id')
                    ->with([
                        'styleInstances',
                        'fieldGroup',
                        'formInstanceFields' => function ($query) {
                            $query->orderBy('order')
                                ->with([
                                    'formField' => function ($query) {
                                        $query->with([
                                            'dataType',
                                            'formFieldValue',
                                            'formFieldDateFormat',
                                        ]);
                                    },
                                    'selectOptionInstances',
                                    'validations',
                                    'conditionals',
                                    'styleInstances',
                                    'formInstanceFieldValue',
                                    'formInstanceFieldDateFormat',
                                ]);
                        }
                    ]);
            },
            'containers' => function ($query) {
                $query->with([
                    'styleInstances',
                    'formInstanceFields' => function ($query) {
                        $query->orderBy('order')
                            ->with([
                                'formField' => function ($query) {
                                    $query->with([
                                        'dataType',
                                        'formFieldValue',
                                        'formFieldDateFormat',
                                    ]);
                                },
                                'selectOptionInstances',
                                'validations',
                                'conditionals',
                                'styleInstances',
                                'formInstanceFieldValue',
                                'formInstanceFieldDateFormat',
                            ]);
                    },
                    'fieldGroupInstances' => function ($query) {
                        $query->with([
                            'styleInstances',
                            'fieldGroup',
                            'formInstanceFields' => function ($query) {
                                $query->orderBy('order')
                                    ->with([
                                        'formField' => function ($query) {
                                            $query->with([
                                                'dataType',
                                                'formFieldValue',
                                                'formFieldDateFormat',
                                            ]);
                                        },
                                        'selectOptionInstances',
                                        'validations',
                                        'conditionals',
                                        'styleInstances',
                                        'formInstanceFieldValue',
                                        'formInstanceFieldDateFormat',
                                    ]);
                            }
                        ]);
                    }
                ]);
            }
        ]);

        $data = array_merge($this->record->toArray(), $data);

        $components = array_merge(
            $this->fillFields($this->record->formInstanceFields),
            $this->fillGroups($this->record->fieldGroupInstances),
            $this->fillContainers($this->record->containers),
        );

        usort($components, function ($a, $b) {
            return $a['data']['order'] <=> $b['data']['order'];
        });

        foreach ($components as &$component) {
            unset($component['order']);
        }

        $data['components'] = $components;

        return $data;
    }

    // Helper functions
    private function fillStyles($styleInstances)
    {
        $styles = [
            'webStyles' => [],
            'pdfStyles' => [],
        ];
        foreach ($styleInstances as $styleInstance) {
            if ($styleInstance->type === 'web') {
                $styles['webStyles'][] = $styleInstance->style_id;
            } elseif ($styleInstance->type === 'pdf') {
                $styles['pdfStyles'][] = $styleInstance->style_id;
            }
        }
        return $styles;
    }

    private function fillValidations($validations)
    {
        $data = [];
        foreach ($validations as $validation) {
            $data[] = [
                'type' => $validation->type,
                'value' => $validation->value,
                'error_message' => $validation->error_message,
            ];
        }
        return $data;
    }

    private function fillConditionals($conditionals)
    {
        $data = [];
        foreach ($conditionals as $conditional) {
            $data[] = [
                'type' => $conditional->type,
                'value' => $conditional->value,
            ];
        }
        return $data;
    }

    private function fillSelectOptionInstances($selectOptionInstances)
    {
        $data = [];
        foreach ($selectOptionInstances as $instance) {
            $data[] = [
                'type' => 'select_option_instance',
                'data' => [
                    'select_option_id' => $instance->select_option_id,
                    'order' => $instance->order
                ],
            ];
        }
        return $data;
    }

    private function fillFields($formFields)
    {
        $components = [];
        foreach ($formFields as $field) {
            $styles = $this->fillStyles($field->styleInstances);
            $validations = $this->fillValidations($field->validations);
            $conditionals = $this->fillConditionals($field->conditionals);
            $selectOptionInstances = $this->fillSelectOptionInstances($field->selectOptionInstances);

            $formField = $field->formField;
            $components[] = [
                'type' => 'form_field',
                'data' => [
                    'form_field_id' => $field->form_field_id,
                    'label' => $formField?->label,
                    'custom_label' => $field->custom_label,
                    'customize_label' => $field->customize_label,
                    'data_binding_path' => $field->data_binding_path,
                    'custom_data_binding_path' => $field->custom_data_binding_path,
                    'customize_data_binding_path' => $field->custom_data_binding_path,
                    'data_binding' => $field->data_binding,
                    'custom_data_binding' => $field->custom_data_binding,
                    'customize_data_binding' => $field->custom_data_binding,
                    'custom_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? $formField->formFieldDateFormat?->date_format,
                    'customize_date_format' => $field->formInstanceFieldDateFormat?->custom_date_format ?? false,
                    'help_text' => $field->help_text,
                    'custom_help_text' => $field->custom_help_text,
                    'customize_help_text' => $field->custom_help_text,
                    'mask' => $field->mask,
                    'custom_mask' => $field->custom_mask,
                    'customize_mask' => $field->custom_mask,
                    'instance_id' => $field->instance_id,
                    'custom_instance_id' => $field->custom_instance_id,
                    'customize_instance_id' => $field->custom_instance_id,
                    'custom_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'customize_field_value' => $field->formInstanceFieldValue?->custom_value,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                    'validations' => $validations,
                    'conditionals' => $conditionals,
                    'select_option_instances' => $selectOptionInstances,
                    'order' => $field->order,
                ],
            ];
        }
        return $components ?? [];
    }

    private function fillGroups($fieldGroups)
    {
        $components = [];
        foreach ($fieldGroups as $group) {
            $groupFields = $group->formInstanceFields;
            $formFieldsData = $this->fillFields($groupFields);

            $styles = $this->fillStyles($group->styleInstances);

            $fieldGroup = $group->fieldGroup;
            $components[] = [
                'type' => 'field_group',
                'data' => [
                    'field_group_id' => $group->field_group_id,
                    'custom_group_label' => $group->custom_group_label,
                    'customize_group_label' => $group->customize_group_label,
                    'repeater' => $group->repeater ?? false,
                    'clear_button' => $group->clear_button ?? false,
                    'custom_repeater_item_label' => $group->custom_repeater_item_label ?? $fieldGroup->repeater_item_label,
                    'customize_repeater_item_label' => $group->custom_repeater_item_label ?? null,
                    'custom_data_binding_path' => $group->custom_data_binding_path ?? $fieldGroup->data_binding_path,
                    'customize_data_binding_path' => $group->custom_data_binding_path ?? null,
                    'custom_data_binding' => $group->custom_data_binding ?? $fieldGroup->data_binding,
                    'customize_data_binding' => $group->custom_data_binding ?? null,
                    'form_fields' => $formFieldsData,
                    'order' => $group->order,
                    'instance_id' => $group->instance_id,
                    'custom_instance_id' => $group->custom_instance_id,
                    'customize_instance_id' => $group->custom_instance_id,
                    'visibility' => $group->visibility,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                ],
            ];
        }
        return $components ?? [];
    }

    private function fillContainers($containers)
    {
        $components = [];
        foreach ($containers as $container) {
            $styles = $this->fillStyles($container->styleInstances);

            $blocks = array_merge(
                $this->fillFields($container->formInstanceFields),
                $this->fillGroups($container->fieldGroupInstances),
            );

            usort($blocks, function ($a, $b) {
                return $a['data']['order'] <=> $b['data']['order'];
            });

            $components[] = [
                'type' => 'container',
                'data' => [
                    'instance_id' => $container->instance_id,
                    'custom_instance_id' => $container->custom_instance_id,
                    'customize_instance_id' => $container->custom_instance_id ?? null,
                    'clear_button' => $container->clear_button ?? false,
                    'components' => $blocks,
                    'webStyles' => $styles['webStyles'],
                    'pdfStyles' => $styles['pdfStyles'],
                    'visibility' => $container->visibility,
                    'order' => $container->order,
                ],
            ];
        }
        return $components ?? [];
    }
}
