<?php

namespace App\Filament\Forms\Imports;

use App\Models\Container;
use App\Models\DataType;
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
use App\Models\Style;
use App\Models\StyleInstance;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Wizard\Step;
use Filament\Forms\Get;
use Filament\Forms\Set;

class FormSchemaImporter
{
    protected $formData;
    protected $fieldMappings = [];
    protected $formVersion = null;
    protected $form = null;
    protected $elementCounter = 1;
    protected $user;

    public function __construct($formData)
    {
        $this->formData = json_decode($formData, true);
        $this->user = Auth::user();
    }

    public static function getFormSchema(): array
    {
        return [
            Wizard::make([
                Step::make('Form Information')
                    ->description('Basic form information')
                    ->schema([
                        Section::make('Form Details')
                            ->schema([
                                TextInput::make('form_id')
                                    ->label('Form ID')
                                    ->required()
                                    ->helperText('The unique ID for this form'),

                                TextInput::make('title')
                                    ->label('Form Title')
                                    ->required()
                                    ->helperText('The title or name of this form'),

                                Select::make('ministry_id')
                                    ->label('Ministry')
                                    ->relationship('ministries', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable(),

                                Toggle::make('create_new_form')
                                    ->label('Create a new form if it doesn\'t exist')
                                    ->default(true)
                                    ->helperText('If the form ID doesn\'t exist, create a new form record'),

                                Toggle::make('create_new_version')
                                    ->label('Create a new version')
                                    ->default(true)
                                    ->helperText('Create a new version even if the form already exists'),
                            ])
                    ]),

                Step::make('Field Mapping')
                    ->description('Map form fields to existing fields')
                    ->schema([
                        Section::make('Field Analysis')
                            ->description('We\'ll analyze each field in your import file and suggest matches')
                            ->schema([
                                // Dynamic content will be generated based on the fields found
                            ])
                    ])
                    ->beforeFormFilled(function (Get $get, Set $set) {
                    }),

                Step::make('Import Preview')
                    ->description('Preview the form structure before importing')
                    ->schema([
                        Section::make('Preview')
                            ->schema([
                                Textarea::make('preview')
                                    ->label('Import Preview')
                                    ->disabled()
                                    ->rows(20)
                                    ->columnSpanFull()
                            ])
                    ])
                    ->afterValidation(function (Get $get, Set $set) {
                        $formStructure = $this->generatePreviewStructure($get('fieldMappings'));
                        $set('preview', json_encode($formStructure, JSON_PRETTY_PRINT));
                    }),

                Step::make('Confirm Import')
                    ->description('Confirm and process the import')
                    ->schema([
                        Section::make('Import Confirmation')
                            ->schema([
                                Toggle::make('confirm_import')
                                    ->label('I confirm this import')
                                    ->required()
                                    ->helperText('Please confirm you want to proceed with this import')
                            ])
                    ]),
            ])
                ->skippable()
                ->persistStepInQueryString()
        ];
    }

    /**
     * Generate preview structure based on field mappings
     */
    protected function generatePreviewStructure($mappings)
    {
        // This would create a preview structure showing what will be imported
        return $this->formData;
    }

    /**
     * Process the form import
     */
    public function processImport(array $data)
    {
        try {
            // Set field mappings if provided
            if (isset($data['field_mappings'])) {
                $this->fieldMappings = $data['field_mappings'];
            }

            // Create or get the form
            $this->createOrGetForm($data);

            // Create a new form version if needed
            if ($data['create_new_version'] ?? true) {
                $this->createFormVersion($data);
            }

            // Process fields based on detected format
            if (isset($this->formData['data']) && isset($this->formData['data']['elements'])) {
                // Process new format fields (adze-template)
                $this->processElements($this->formData['data']['elements']);
            } elseif (isset($this->formData['fields'])) {
                // Process legacy format fields
                $this->processFields($this->formData['fields']);
            } else {
                throw new \Exception("No valid fields structure found in import data");
            }

            return [
                'success' => true,
                'message' => "Form '{$this->form->form_title}' imported successfully.",
                'form' => $this->form,
                'formVersion' => $this->formVersion,
            ];
        } catch (\Exception $e) {
            Log::error("Import failed: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            return [
                'success' => false,
                'message' => "Import failed: " . $e->getMessage(),
            ];
        }
    }

    /**
     * Process elements from the adze-template format
     */
    protected function processElements(array $elements, $parentContainer = null, $parentGroup = null, $order = 0)
    {
        foreach ($elements as $index => $element) {
            $currentOrder = $order + $index;

            // Check if this is a container
            if (isset($element['elementType']) && $element['elementType'] === 'ContainerFormElements') {
                // Create container
                $container = $this->createContainer([
                    'id' => $element['token'] ?? null,
                    'name' => $element['name'] ?? '',
                    'label' => $element['label'] ?? '',
                    'webStyles' => $element['webStyles'] ?? [],
                    'pdfStyles' => $element['pdfStyles'] ?? [],
                    'conditions' => $element['conditions'] ?? null,
                    'visibility' => isset($element['isVisible']) ? ($element['isVisible'] ? 'visible' : 'hidden') : 'visible',
                ], $currentOrder);

                // Process child elements if they exist
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $this->processElements($element['elements'], $container->id, null, 0);
                }
            }
            // Otherwise it's a field
            else {
                $fieldId = $element['token'] ?? md5(json_encode($element));
                $mapping = $this->fieldMappings[$fieldId] ?? 'new';

                // Determine field type using improved mapping
                $fieldType = $this->mapType(
                    $element['elementType'] ?? $element['type'] ?? $element['dataFormat'] ?? 'text-input'
                );

                // Build field data for creation
                $field = [
                    'id' => $fieldId,
                    'name' => $element['name'] ?? '',
                    'label' => $element['label'] ?? '',
                    'type' => $fieldType,
                    'help_text' => $element['helpText'] ?? '',
                    'description' => $element['description'] ?? '',
                    'validations' => $element['validations'] ?? [],
                    'data_binding' => $element['dataBinding'] ?? null,
                    'options' => $element['options'] ?? $element['listItems'] ?? [],
                    'isVisible' => $element['isVisible'] ?? true,
                    'isEnabled' => $element['isEnabled'] ?? true,
                    'isReadOnly' => $element['isReadOnly'] ?? false,
                    'repeating' => $element['repeats'] ?? false,
                    'defaultValue' => $element['defaultValue'] ?? null,
                ];

                // Find or create the field according to mapping
                if ($mapping !== 'new' && is_numeric($mapping)) {
                    $formField = FormField::find($mapping);
                    if (!$formField) {
                        throw new \Exception("Mapped field ID {$mapping} not found");
                    }
                } else {
                    $formField = $this->findOrCreateField($field);
                }

                // Always associate the field to the new version
                $this->createFormInstanceField($field, $formField, $currentOrder, $parentContainer, $parentGroup);
            }
        }
    }

    /**
     * Create or get a form based on the form ID
     */
    protected function createOrGetForm($data)
    {
        $formId = $data['form_id'] ?? $this->formData['form_id'] ?? null;
        $title = $data['title'] ?? $this->formData['title'] ?? null;
        $ministryId = $data['ministry_id'] ?? $this->formData['ministry_id'] ?? null;
        $createNew = $data['create_new_form'] ?? true;

        if (!$formId) {
            throw new \Exception("No form ID provided");
        }

        $form = Form::where('form_id', $formId)->first();

        if (!$form && $createNew) {
            $form = Form::create([
                'form_id' => $formId,
                'form_title' => $title,
                'ministry_id' => $ministryId,
            ]);

            Notification::make()
                ->title('New Form Created')
                ->body("Created form '{$form->form_id}'")
                ->success()
                ->send()
                ->sendToDatabase($this->user);
        } elseif (!$form) {
            throw new \Exception("Form '{$formId}' does not exist");
        }

        $this->form = $form;
    }

    /**
     * Create a new form version
     */
    protected function createFormVersion($data)
    {
        $this->formVersion = FormVersion::create([
            'form_id' => $this->form->id,
            'status' => 'draft',
            'form_developer_id' => $this->user ? $this->user->id : null,
        ]);

        if (isset($this->formData['dataSources'])) {
            foreach ($this->formData['dataSources'] as $item) {
                $source = FormDataSource::where('name', $item['name'])->first();
                if ($source) {
                    $this->formVersion->formDataSources()->attach($source['id']);
                }
            }
        }
    }

    /**
     * Process the field hierarchy recursively
     */
    protected function processFields(array $fields, $parentContainer = null, $parentGroup = null, $order = 0)
    {
        foreach ($fields as $index => $field) {
            $order = $index;

            switch ($field['type']) {
                case 'container':
                    $container = $this->createContainer($field, $order);

                    if (isset($field['children'])) {
                        $this->processFields($field['children'], $container, null, 0);
                    }
                    break;

                case 'dropdown':
                case 'radio':
                case 'checkbox':
                    $formField = $this->findOrCreateField($field);
                    $this->createFormInstanceField($field, $formField, $order, $parentContainer, $parentGroup);
                    break;

                default:
                    $formField = $this->findOrCreateField($field);
                    $this->createFormInstanceField($field, $formField, $order, $parentContainer, $parentGroup);
                    break;
            }
        }
    }

    /**
     * Find a matching field or create a new one
     */
    protected function findOrCreateField($field)
    {
        $dataTypeName = $this->mapType($field['type']);
        $dataType = DataType::where('name', $dataTypeName)->first();

        if (!$dataType) {
            // Fallback to 'text' if mapping fails
            $dataType = DataType::where('name', 'text')->first();
            if (!$dataType) {
                throw new \Exception("Data type for '{$field['type']}' not found and fallback to 'text' also failed");
            }
        }

        // Try to find an existing field with similar properties
        $formField = FormField::where('name', $field['name'])
            ->orWhere('label', $field['label'])
            ->where('data_type_id', $dataType->id)
            ->first();

        // If we didn't find a match or we have a specific mapping, create a new field
        if (!$formField) {
            $formField = FormField::create([
                'name' => "imported_{$field['name']}",
                'label' => $field['label'] ?? $field['name'],
                'data_type_id' => $dataType->id,
                'help_text' => $field['help_text'] ?? null,
                'description' => "Imported from {$this->formData['form_id']} on " . now()->toDateTimeString(),
            ]);
        }

        return $formField;
    }

    /**
     * Create a container instance
     */
    protected function createContainer($container, $order)
    {
        $validId = $this->isValidIdFormat($container['id']);

        // Compose visibility condition if present
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
            'form_version_id' => $this->formVersion->id,
            'order' => $order,
            'instance_id' => $validId ? $container['id'] : 'element' . $this->elementCounter++,
            'custom_instance_id' => $validId ? null : $container['id'],
            'visibility' => $visibility,
        ]);

        // Create style instances
        if (isset($container['webStyles'])) {
            $this->createStyles($container['webStyles'], 'web', containerID: $newContainer->id);
        }

        if (isset($container['pdfStyles'])) {
            $this->createStyles($container['pdfStyles'], 'pdf', containerID: $newContainer->id);
        }

        return $newContainer;
    }

    /**
     * Create a group instance
     */
    protected function createGroup($group, $order, $containerID = null)
    {
        $validId = $this->isValidIdFormat($group['id']);

        $generic = FieldGroup::where('name', 'generic_group')->first();
        $template = FieldGroup::where('name', $group['codeContext']['name'] ?? null)->first();

        // Compose visibility condition if present
        $visibility = null;
        if (isset($group['conditions'])) {
            foreach ($group['conditions'] as $condition) {
                if ($condition['type'] === 'visibility') {
                    $visibility = $condition['value'];
                }
            }
        }

        // Create group instance
        $newGroup = FieldGroupInstance::create([
            'form_version_id' => $this->formVersion->id,
            'field_group_id' => $template ? $template->id : $generic->id,
            'container_id' => $containerID,
            'order' => $order,
            'repeater' => $group['repeating'] ?? $group['is_repeating'] ?? false,
            'visibility' => $visibility,
            'instance_id' => $validId ? $group['id'] : 'element' . $this->elementCounter++,
            'custom_instance_id' => $validId ? null : $group['id'],
            'custom_data_binding' => $group['binding_ref'] ?? null,
        ]);

        // Create style instances
        if (isset($group['webStyles'])) {
            $this->createStyles($group['webStyles'], 'web', groupID: $newGroup->id);
        }

        if (isset($group['pdfStyles'])) {
            $this->createStyles($group['pdfStyles'], 'pdf', groupID: $newGroup->id);
        }

        return $newGroup;
    }

    /**
     * Create a form instance field
     */
    protected function createFormInstanceField($field, $formField, $order, $container = null, $group = null)
    {
        // Fix: $container and $group may be IDs (int) or objects; get their IDs if needed
        $containerId = is_object($container) && property_exists($container, 'id') ? $container->id : (is_int($container) ? $container : null);
        $groupId = is_object($group) && property_exists($group, 'id') ? $group->id : (is_int($group) ? $group : null);

        $validId = $this->isValidIdFormat($field['id']);

        $newField = FormInstanceField::create([
            'form_version_id' => $this->formVersion->id,
            'form_field_id' => $formField->id,
            'field_group_instance_id' => $groupId,
            'container_id' => $containerId,
            'order' => $order,
            'instance_id' => $validId ? $field['id'] : 'element' . $this->elementCounter++,
            'custom_instance_id' => $validId ? null : $field['id'],
            'customize_label' => 'customize',
            'custom_label' => $field['label'],
            'custom_data_binding' => $field['binding_ref'] ?? null,
            'custom_help_text' => $field['help_text'] ?? null,
        ]);

        // Create default value if present
        if (isset($field['value'])) {
            FormInstanceFieldValue::create([
                'form_instance_field_id' => $newField->id,
                'custom_value' => $field['value'],
            ]);
        }

        // Create validations if present
        if (isset($field['validations'])) {
            foreach ($field['validations'] as $validation) {
                FormInstanceFieldValidation::create([
                    'form_instance_field_id' => $newField->id,
                    'type' => $validation['type'] ?? 'custom',
                    'value' => $validation['value'] ?? null,
                    'error_message' => $validation['errorMessage'] ?? 'Invalid input',
                ]);
            }
        }

        // Create conditionals if present
        if (isset($field['conditions'])) {
            foreach ($field['conditions'] as $conditional) {
                FormInstanceFieldConditionals::create([
                    'form_instance_field_id' => $newField->id,
                    'type' => $conditional['type'] ?? 'visibility',
                    'value' => $conditional['value'] ?? null,
                ]);
            }
        }

        // Create style instances if present
        if (isset($field['webStyles'])) {
            $this->createStyles($field['webStyles'], 'web', fieldID: $newField->id);
        }

        if (isset($field['pdfStyles'])) {
            $this->createStyles($field['pdfStyles'], 'pdf', fieldID: $newField->id);
        }

        // Create select options if present
        if (in_array($field['type'], ['dropdown', 'radio', 'checkbox']) && isset($field['options'])) {
            $this->createSelectOptions($field['options'], $newField->id);
        }

        return $newField;
    }

    /**
     * Create style instances
     */
    protected function createStyles($styles, $type, $fieldID = null, $groupID = null, $containerID = null)
    {
        foreach ($styles as $property => $value) {
            // Check if style exists, create if not
            $style = Style::firstOrCreate(
                ['property' => $property, 'value' => $value],
                ['name' => "{$property} {$value}"]
            );

            // Create style instance
            StyleInstance::create([
                'type' => $type,
                'style_id' => $style->id,
                'form_instance_field_id' => $fieldID,
                'field_group_instance_id' => $groupID,
                'container_id' => $containerID,
            ]);
        }
    }

    /**
     * Create select options
     */
    protected function createSelectOptions($options, $fieldID)
    {
        foreach ($options as $index => $option) {
            // Fix: Ensure label is always a string, not an array
            if (is_array($option)) {
                $optionName = $option['name'] ?? "option_{$index}";
                // If label is an array, flatten to string
                $optionLabel = $option['label'] ?? $option['text'] ?? $option['name'] ?? $option['value'] ?? "Option {$index}";
                if (is_array($optionLabel)) {
                    $optionLabel = json_encode($optionLabel);
                }
                $optionValue = $option['value'] ?? $index;
            } else {
                $optionName = "option_{$index}";
                $optionLabel = (string)$option;
                $optionValue = $index;
            }

            // Fix: Ensure all values are strings or scalars
            if (is_array($optionValue)) {
                $optionValue = json_encode($optionValue);
            }

            // Check if SelectOption exists, create if not
            $selectOption = SelectOptions::firstOrCreate(
                ['name' => $optionName],
                [
                    'label' => $optionLabel,
                    'value' => $optionValue,
                    'description' => "Generated by Form Importer"
                ]
            );

            // Create select option instance
            SelectOptionInstance::create([
                'select_option_id' => $selectOption->id,
                'form_instance_field_id' => $fieldID,
                'order' => $index + 1,
            ]);
        }
    }

    /**
     * Check if ID is in valid element format
     */
    protected function isValidIdFormat($id)
    {
        if (str_starts_with($id, 'element')) {
            $numPart = substr($id, 7);
            return $numPart !== '' && ctype_digit($numPart);
        }

        return false;
    }

    /**
     * Map input type to system data type
     */
    protected function mapType($type)
    {
        // Normalize type for Adze/JSON import
        $type = strtolower($type);

        // Adze/JSON elementType mapping
        $adzeMap = [
            'textinputformelements' => 'text-input',
            'selectinputformelements' => 'dropdown',
            'radioinputformelements' => 'radio',
            'checkboxinputformelements' => 'checkbox',
            'textareainputformelements' => 'textarea',
            'dateinputformelements' => 'date',
            'containerformelements' => 'container',
        ];

        if (isset($adzeMap[$type])) {
            return $adzeMap[$type];
        }

        // Legacy/other mapping
        $mapping = [
            'text-input' => 'text-input',
            'text' => 'text-input',
            'dropdown' => 'dropdown',
            'select' => 'dropdown',
            'radio' => 'radio',
            'checkbox' => 'checkbox',
            'textarea' => 'textarea',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime',
            'number' => 'number',
            'email' => 'email',
            'tel' => 'tel',
            'url' => 'url',
            'file' => 'file',
            'image' => 'image',
            'container' => 'container',
        ];

        return $mapping[$type] ?? 'text-input';
    }
}
