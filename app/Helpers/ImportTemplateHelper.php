<?php

namespace App\Helpers;

use App\Models\Container;
use App\Models\FieldGroup;
use App\Models\FieldGroupInstance;
use App\Models\Form;
use App\Models\FormDataSource;
use App\Models\FormField;
use App\Models\FormInstanceField;
use App\Models\FormInstanceFieldConditionals;
use App\Models\FormInstanceFieldValidation;
use App\Models\FormInstanceFieldValue;
use App\Models\FormVersion;
use App\Models\SelectOptionInstance;
use App\Models\SelectOptions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ImportTemplateHelper
{
    public static function importForm($string)
    {
        $json = json_decode($string, true);

        $user = Auth::user();
        $form = Form::firstOrCreate(
            ['form_id' => $json['form_id']],
            ['form_title' => $json['title'], 'ministry_id' => $json['ministry_id'] ?? null]
        );

        if (!$form) {
            Notification::make()
                ->title('Error')
                ->body("Failed to create new Form `{$form['form_id']}`.")
                ->danger()
                ->send()
                ->sendToDatabase($user);
        } elseif ($form->wasRecentlyCreated) {
            Notification::make()
                ->title('New Form Created')
                ->body("New Form `{$form['form_id']}` created successfully.")
                ->warning()
                ->send()
                ->sendToDatabase($user);
        }

        $elementNum = 1; // Initialize elementNum counter. Passed by reference to element creator functions.

        $formVersion = FormVersion::create([
            'form_id' => $form->id,
            'deployed_to' => $json['deployed_to'] ?? null,
        ]);

        if ($formVersion) {
            Notification::make()
                ->title('Success')
                ->body("FormVersion {$formVersion['id']} created successfully!")
                ->success()
                ->send()
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->title('Error')
                ->body('Failed to create FormVersion.')
                ->danger()
                ->send()
                ->sendToDatabase($user);
            return;
        }

        // Create form_version_form_data_sources
        if (isset($json['dataSources'])) {
            foreach ($json['dataSources'] as $item) {
                // Find data source with matching name
                $source = FormDataSource::where('name', $item['name'])->first();
                $formVersion->formDataSources()->attach($source['id']);
            }
        }

        // Create nested elements
        foreach ($json['data']['items'] as $index => $element) {
            if ($element['type'] === 'container') {
                self::createContainer(
                    $user,
                    $formVersion->id,
                    $element,
                    $elementNum,
                    $index,
                );
            } elseif ($element['type'] === 'group') {
                self::createGroup(
                    user: $user,
                    formVersionID: $formVersion->id,
                    group: $element,
                    elementNum: $elementNum,
                    order: $index,
                );
            } else {
                self::createField(
                    user: $user,
                    formVersionID: $formVersion->id,
                    field: $element,
                    elementNum: $elementNum,
                    order: $index,
                );
            }
        }

        return $formVersion;
    }

    private static function createContainer($user, $formVersionID, $container, &$elementNum, $order)
    {
        $validID = self::isValidIdFormat($container['id']);

        // Compose visibility
        $visibility = null;
        if (isset($container['conditions'])) {
            foreach ($container['conditions'] as $condition) {
                if ($condition['type'] === 'visibility') {
                    $visibility = $condition['value'];
                }
            }
        }

        // Create container
        $newContainer = Container::create([
            'form_version_id' => $formVersionID,
            'order' => $order,
            'instance_id' => $validID ? $container['id'] : 'element' . $elementNum,
            'custom_instance_id' => $validID ? null : $container['id'],
            'visibility' => $visibility,
        ]);

        if (!$newContainer) {
            Notification::make()->title('Error')->body('Failed to create Container.')->danger()->send()->sendToDatabase($user);
        } else {
            $elementNum++;

            // Create nested elements
            foreach ($container['containerItems'] as $index => $element) {
                if ($element['type'] === 'group') {
                    self::createGroup(
                        user: $user,
                        formVersionID: $formVersionID,
                        group: $element,
                        elementNum: $elementNum,
                        order: $index,
                        containerID: $newContainer->id,
                    );
                } else {
                    self::createField(
                        user: $user,
                        formVersionID: $formVersionID,
                        field: $element,
                        elementNum: $elementNum,
                        order: $index,
                        containerID: $newContainer->id,
                    );
                }
            }
        }
    }

    private static function createGroup($user, $formVersionID, $group, &$elementNum, $order, $containerID = null)
    {
        $validID = self::isValidIdFormat($group['id']);

        $generic = FieldGroup::where('name', 'generic_group')->first();
        $template = FieldGroup::where('name', $group['codeContext']['name'])->first();

        // Compose visibility
        $visibility = null;
        if (isset($group['conditions'])) {
            foreach ($group['conditions'] as $condition) {
                if ($condition['type'] === 'visibility') {
                    $visibility = $condition['value'];
                }
            }
        }

        // Compose label
        ['customize' => $customizeGroupLabel, 'custom' => $customGroupLabel] = self::composeLabel($group, $template, $generic);

        // Create group
        $newGroup = FieldGroupInstance::create([
            'form_version_id' => $formVersionID,
            'field_group_id' => $template ? $template->id : $generic->id,
            'container_id' => $containerID,
            'order' => $order,
            'repeater' => $group['repeater'] ?? false,
            'visibility' => $visibility,
            'instance_id' => $validID ? $group['id'] : 'element' . $elementNum,
            'custom_instance_id' => $validID ? null : $group['id'],
            'custom_data_binding' => $group['databindings']['source'] ?? null,
            'custom_data_binding_path' => $group['databindings']['path'] ?? null,
            'custom_repeater_item_label' => $group['repeaterItemLabel'] ?? null,
            'customize_group_label' => $customizeGroupLabel,
            'custom_group_label' => $customGroupLabel === 'hide' ? null : $customGroupLabel,
        ]);

        if (!$newGroup) {
            Notification::make()->title('Error')->body('Failed to create Group.')->danger()->send()->sendToDatabase($user);
        } else {
            $elementNum++;

            // Create nested elements
            foreach ($group['groupItems'][0]['fields'] as $index => $element) {
                self::createField(
                    user: $user,
                    formVersionID: $formVersionID,
                    field: $element,
                    elementNum: $elementNum,
                    order: $index,
                    groupID: $newGroup->id,
                );
            }
        }
    }

    private static function createField($user, $formVersionID, $field, &$elementNum, $order, $groupID = null, $containerID = null)
    {
        $validID = self::isValidIdFormat($field['id']);
        $type = str_replace('-', '_', $field['type']);
        $generic = FormField::with('dataType')->where('name', "generic_{$type}")->first();
        $template = FormField::with('dataType')->where('name', $field['codeContext']['name'])->first();

        ['customize' => $customizeFieldLabel, 'custom' => $customFieldLabel] = self::composeLabel($field, $template, $generic);

        $newField = FormInstanceField::create([
            'form_version_id' => $formVersionID,
            'form_field_id' => $template ? $template->id : $generic->id,
            'field_group_instance_id' => $groupID,
            'container_id' => $containerID,
            'order' => $order,
            'instance_id' => $validID ? $field['id'] : 'element' . $elementNum,
            'custom_instance_id' => $validID ? null : $field['id'],
            'customize_label' => $customizeFieldLabel,
            'custom_label' => $customFieldLabel === 'hide' ? null : $customFieldLabel,
            'custom_data_binding' => $field['databindings']['source'] ?? null,
            'custom_data_binding_path' => $field['databindings']['path'] ?? null,
            'custom_mask' => $field['mask'] ?? null,
            'custom_help_text' => $field['helperText'] ?? null,
        ]);

        if (!$newField) {
            Notification::make()->title('Error')->body('Failed to create Field.')->danger()->send()->sendToDatabase($user);
        } else {
            $elementNum++;

            // Create form_instance_field_value
            if (isset($field['value'])) {
                $newValue = FormInstanceFieldValue::create([
                    'form_instance_field_id' => $newField->id,
                    'custom_value' => $field['value'],
                ]);
                if (!$newValue) {
                    Notification::make()
                        ->title('Error')
                        ->body("Failed to create Value for field #{$newField->id}.")
                        ->danger()
                        ->send()
                        ->sendToDatabase($user);
                }
            }

            // Create form_instance_field_validations
            if (isset($field['validation'])) {
                foreach ($field['validation'] as $validation) {
                    $newValidation = FormInstanceFieldValidation::create([
                        'form_instance_field_id' => $newField->id,
                        'type' => $validation['type'],
                        'value' => $validation['value'],
                        'error_message' => $validation['errorMessage'],
                    ]);
                    if (!$newValidation) {
                        Notification::make()
                            ->title('Error')
                            ->body("Failed to create Validation {$validation['type']} for field #{$newField->id}.")
                            ->danger()
                            ->send()
                            ->sendToDatabase($user);
                    }
                }
            }

            // Create form_instance_field_conditionals
            if (isset($field['conditions'])) {
                foreach ($field['conditions'] as $conditional) {
                    $newConditionals = FormInstanceFieldConditionals::create([
                        'form_instance_field_id' => $newField->id,
                        'type' => $conditional['type'],
                        'value' => $conditional['value'],
                    ]);
                    if (!$newConditionals) {
                        Notification::make()
                            ->title('Error')
                            ->body("Failed to create Conditional `{$conditional['type']}` for field #{$newField->id}.")
                            ->danger()
                            ->send()
                            ->sendToDatabase($user);
                    }
                }
            }

            // Create select_option_instances
            if (isset($field['listItems'])) {
                self::createSelectOptions($user, $field['listItems'], $newField->id, $formVersionID);
            }
        }
    }

    private static function createSelectOptions($user, $options, $fieldID, $formVersionID)
    {
        foreach ($options as $index => $option) {
            // Check if SelectOption exists, create if not
            $selectOption = SelectOptions::firstOrCreate(
                ['name' => $option['name']],
                [
                    'label' => $option['text'],
                    'value' => $option['value'],
                    'description' => "Generated by Form Importer for form version {$formVersionID}."
                ],
            );

            if (!$selectOption) {
                Notification::make()
                    ->title('Error')
                    ->body("Failed to create SelectOption `{$option['name']}`.")
                    ->danger()
                    ->send()
                    ->sendToDatabase($user);
            } elseif ($selectOption->wasRecentlyCreated) {
                Notification::make()
                    ->title('Error')
                    ->body("Creating new SelectOption: `{$option['name']}`.")
                    ->warning()
                    ->send()
                    ->sendToDatabase($user);
            }

            // Create select_option_instance
            $newSelectOptionInstance = SelectOptionInstance::create([
                'select_option_id' => $selectOption->id,
                'form_instance_field_id' => $fieldID,
                'order' => $index + 1,
            ]);
            if (!$newSelectOptionInstance) {
                Notification::make()
                    ->title('Error')
                    ->body("Failed to create SelectOption `{$option['text']}: {$option['value']}`.")
                    ->danger()
                    ->send()
                    ->sendToDatabase($user);
            }
        }
    }

    private static function isValidIdFormat($id)
    {
        // Check that ID is in format 'element' . $num
        if (str_starts_with($id, 'element')) {
            $numPart = substr($id, 7); // Get the part after "element"

            if ($numPart !== '' && ctype_digit($numPart)) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private static function composeLabel($element, $template, $generic)
    {
        // Compose label
        $customizeLabel = 'hide';
        $customLabel = null;
        if ($element['label'] !== null) {
            if ($template && $element['label'] === $template['label']) {
                $customizeLabel = 'default';
            } elseif ($generic && $element['label'] === $generic['label']) {
                $customizeLabel = 'default';
            } else {
                $customizeLabel = 'customize';
                $customLabel = $element['label'];
            }
        }

        return ['customize' => $customizeLabel, 'custom' => $customLabel];
    }
}
