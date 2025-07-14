<?php

namespace App\Jobs;

use App\Models\FormBuilding\FormVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\FormBuilding\FormElement;

class ImportFormVersionElementsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected $formVersionId;
    protected $schemaContent;
    protected $cacheKey;
    protected $userId;

    public function __construct($formVersionId, $schemaContent, $cacheKey, $userId)
    {
        $this->formVersionId = $formVersionId;
        $this->schemaContent = $schemaContent;
        $this->cacheKey = $cacheKey;
        $this->userId = $userId;
    }

    public function handle()
    {
        try {
            Cache::put($this->cacheKey . '_status', 'processing', 3600);
            Cache::put($this->cacheKey . '_progress', 'Starting import...', 3600);

            $formVersion = FormVersion::findOrFail($this->formVersionId);
            $parsed = json_decode($this->schemaContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON content: ' . json_last_error_msg());
            }

            // Process data sources and javascript
            $this->processDataSources($parsed, $formVersion);
            $this->processJavaScript($parsed, $formVersion);

            // Get the root elements array
            $elements = [];
            if (isset($parsed['data']['elements'])) {
                $elements = $parsed['data']['elements'];
            } elseif (isset($parsed['fields'])) {
                $elements = $parsed['fields'];
            } elseif (isset($parsed['elements'])) {
                $elements = $parsed['elements'];
            }

            if (empty($elements) || !is_array($elements)) {
                throw new \Exception('No elements found in schema or invalid format.');
            }

            Cache::put($this->cacheKey . '_progress', 'Found ' . count($elements) . ' elements to import...', 3600);

            // Count total elements for progress tracking
            $totalElements = $this->countElementsRecursive($elements);
            $processedElements = 0;

            // Import elements with progress tracking
            $processedElements = $this->importElementsRecursive($elements, null, $formVersion, $processedElements, $totalElements);

            Cache::put($this->cacheKey . '_progress', "Completed: {$processedElements}/{$totalElements} elements", 3600);
            Cache::put($this->cacheKey . '_status', 'complete', 3600);
        } catch (\Throwable $e) {
            Cache::put($this->cacheKey . '_status', 'error', 3600);
            Cache::put($this->cacheKey . '_error', $e->getMessage(), 3600);
        }
    }

    /**
     * Process data sources from the schema
     */
    private function processDataSources(array $parsed, $formVersion): void
    {
        // Look for data sources in multiple possible locations
        $dataSources = null;
        if (isset($parsed['data']['dataSources'])) {
            $dataSources = $parsed['data']['dataSources'];
        } elseif (isset($parsed['dataSources'])) {
            $dataSources = $parsed['dataSources'];
        }

        if (!$dataSources || !is_array($dataSources)) {
            return;
        }

        // Clear existing data source associations
        $formVersion->formDataSources()->detach();

        foreach ($dataSources as $index => $dataSourceData) {
            try {
                // Create or find the data source
                $dataSource = \App\Models\FormMetadata\FormDataSource::firstOrCreate([
                    'name' => $dataSourceData['name'] ?? 'Imported Data Source ' . ($index + 1),
                    'type' => $dataSourceData['type'] ?? 'json',
                ], [
                    'description' => $dataSourceData['description'] ?? 'Imported from template',
                    'endpoint' => $dataSourceData['endpoint'] ?? null,
                    'params' => isset($dataSourceData['params']) ? json_encode($dataSourceData['params']) : null,
                    'body' => isset($dataSourceData['body']) ? json_encode($dataSourceData['body']) : null,
                    'headers' => isset($dataSourceData['headers']) ? json_encode($dataSourceData['headers']) : null,
                    'host' => $dataSourceData['host'] ?? null,
                ]);

                // Associate with form version
                $formVersion->formDataSources()->attach($dataSource->id, ['order' => $index + 1]);
            } catch (\Exception $e) {
                Log::warning('Failed to process data source', [
                    'data_source' => $dataSourceData,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Process JavaScript from the schema
     */
    private function processJavaScript(array $parsed, $formVersion): void
    {
        // Look for JavaScript in multiple possible locations
        $javascript = null;
        if (isset($parsed['data']['javascript'])) {
            $javascript = $parsed['data']['javascript'];
        } elseif (isset($parsed['javascript'])) {
            $javascript = $parsed['javascript'];
        }

        if (!$javascript || !is_array($javascript)) {
            return;
        }

        // Combine all JavaScript sections into one script
        $combinedScript = "// Imported JavaScript from template\n\n";

        foreach ($javascript as $sectionName => $jsContent) {
            if (!empty($jsContent)) {
                $combinedScript .= "// Section: {$sectionName}\n";
                $combinedScript .= $jsContent . "\n\n";
            }
        }

        // Only create script if there's content
        if (trim($combinedScript) !== "// Imported JavaScript from template") {
            try {
                if (!class_exists(\App\Models\FormBuilding\FormScript::class)) {
                    throw new \Exception('FormScript class not found');
                }

                // Create script (web by default)
                \App\Models\FormBuilding\FormScript::createFormScript($formVersion, $combinedScript, 'web');
            } catch (\Exception $e) {
                Log::error('Failed to create JavaScript form script', [
                    'form_version_id' => $formVersion->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::info('No JavaScript content to create script for');
        }
    }

    // Add method to count total elements for progress tracking
    protected function countElementsRecursive(array $elements): int
    {
        $count = 0;
        foreach ($elements as $element) {
            $count++;

            // Count children/nested elements
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $count += $this->countElementsRecursive($element['elements']);
            } elseif (!empty($element['children']) && is_array($element['children'])) {
                $count += $this->countElementsRecursive($element['children']);
            }
        }
        return $count;
    }

    /**
     * Check if element is a button type
     */
    private function isButtonElement($elementType): bool
    {
        return $elementType === \App\Models\FormBuilding\ButtonInputFormElement::class ||
            $elementType === 'ButtonInputFormElements' ||
            $elementType === 'button';
    }

    /**
     * Check if button element has '+' or '-' label
     */
    private function isPlusMinusButton(array $element): bool
    {
        $label = trim($element['label'] ?? $element['name'] ?? '');
        return $label === '+' || $label === '-';
    }

    /**
     * Check if container element has '+' or '-' label
     */
    private function isPlusMinusContainer(array $element): bool
    {
        $label = trim($element['label'] ?? $element['name'] ?? '');
        return $label === '+' || $label === '-';
    }

    /**
     * Check if element is a container (should not create form field)
     */
    private function isContainerElement(string $elementType, array $element): bool
    {
        $containerTypes = [
            'ContainerFormElements',
            \App\Models\FormBuilding\ContainerFormElement::class,
            'container',
            'section',
            'group',
            'fieldset'
        ];

        // Check element type directly
        if (in_array($elementType, $containerTypes)) {
            return true;
        }

        // Check the actual resolved type
        $resolvedType = $this->resolveElementableType($elementType);
        if ($resolvedType === \App\Models\FormBuilding\ContainerFormElement::class) {
            return true;
        }

        // Check container type property
        if (isset($element['containerType'])) {
            return true;
        }

        return false;
    }



    // Updated to include progress tracking
    protected function importElementsRecursive(array $elements, $parentId, $formVersion, $processedElements = 0, $totalElements = 0, $inRepeatableContainer = false, $inPlusContainer = false)
    {
        foreach ($elements as $element) {
            try {
                // Update progress
                $processedElements++;
                if ($totalElements > 0) {
                    $percentage = round(($processedElements / $totalElements) * 100);
                    Cache::put($this->cacheKey . '_progress', "Processing: {$processedElements}/{$totalElements} ({$percentage}%)", 3600);
                }

                // Check if this is a +/- element
                // Always skip +/- buttons
                $label = trim($element['label'] ?? '');
                $name = trim($element['name'] ?? '');
                $elementType = $element['elementType'] ?? $element['type'] ?? '';
                $isPlusMinusElement = ($label === '+' || $label === '-' || $name === '+' || $name === '-');
                if ($isPlusMinusElement) {
                    if ($elementType === 'ButtonInputFormElements' || $elementType === 'button') {
                        continue;
                    }

                    // For +/- containers: skip if we're already inside another +/- container
                    if ($elementType === 'ContainerFormElements' || $elementType === 'container') {
                        if ($inPlusContainer) {
                            // Process children directly under current parent
                            if (!empty($element['elements']) || !empty($element['children'])) {
                                $childElements = $element['elements'] ?? $element['children'];
                                if (is_array($childElements)) {
                                    $processedElements = $this->importElementsRecursive($childElements, $parentId, $formVersion, $processedElements, $totalElements, $inRepeatableContainer, $inPlusContainer);
                                }
                            }
                            continue;
                        }
                    }
                }
                $type = $this->resolveElementableType($elementType);

                // Fallback for lowercase/short types
                if (!$type) {
                    $typeMap = [
                        'container' => \App\Models\FormBuilding\ContainerFormElement::class,
                        'text-input' => \App\Models\FormBuilding\TextInputFormElement::class,
                        'textarea' => \App\Models\FormBuilding\TextareaInputFormElement::class,
                        'radio' => \App\Models\FormBuilding\RadioInputFormElement::class,
                        'dropdown' => \App\Models\FormBuilding\SelectInputFormElement::class,
                        'select' => \App\Models\FormBuilding\SelectInputFormElement::class,
                        'checkbox' => \App\Models\FormBuilding\CheckboxInputFormElement::class,
                        'date' => \App\Models\FormBuilding\DateSelectInputFormElement::class,
                        'number' => \App\Models\FormBuilding\NumberInputFormElement::class,
                        'html' => \App\Models\FormBuilding\HTMLFormElement::class,
                        'text-info' => \App\Models\FormBuilding\TextInfoFormElement::class,
                        'button' => \App\Models\FormBuilding\ButtonInputFormElement::class,
                    ];
                    if (isset($typeMap[$elementType])) {
                        $type = $typeMap[$elementType];
                    }
                }

                if (!$type) {
                    continue;
                }

                $isRepeatableContainer = false;
                if ($type === \App\Models\FormBuilding\ContainerFormElement::class) {
                    $isRepeatableContainer = $this->isRepeatableContainer($element);
                }

                if ($inRepeatableContainer && $this->isTextField($type)) {
                    if (!empty($element['elements']) || !empty($element['children'])) {
                        $childElements = $element['elements'] ?? $element['children'];
                        if (is_array($childElements)) {
                            $processedElements = $this->importElementsRecursive($childElements, $parentId, $formVersion, $processedElements, $totalElements, $inRepeatableContainer, $inPlusContainer);
                        }
                    }
                    continue;
                }

                $attributes = $this->extractElementAttributes($element);

                // Extract data binding information before creating the element
                $dataBindingInfo = $this->extractDataBindingInfo($element);

                $options = [];
                if (!empty($element['listItems']) && is_array($element['listItems'])) {
                    $options = $element['listItems'];
                } elseif (!empty($element['options']) && is_array($element['options'])) {
                    $options = $element['options'];
                }

                // Get the human-readable label for both name and label fields
                $humanReadableLabel = null;

                // Special handling for TextInfo elements - use content if it's short
                if ($type === \App\Models\FormBuilding\TextInfoFormElement::class && isset($element['content'])) {
                    $content = trim($element['content']);
                    if (!empty($content) && strlen($content) <= 30) {
                        $humanReadableLabel = $content;
                    }
                }

                // Fallback to label/name if not set yet
                if (!$humanReadableLabel) {
                    if (isset($element['label']) && $element['label'] !== '') {
                        $humanReadableLabel = $element['label'];
                    } elseif (isset($element['name']) && $element['name'] !== '') {
                        $humanReadableLabel = $element['name'];
                    } else {
                        $humanReadableLabel = 'Imported Element';
                    }
                }

                $technicalName = $element['name'] ?? $humanReadableLabel;

                $elementData = [
                    'form_version_id' => $formVersion->id,
                    'parent_id' => $parentId,
                    'name' => $humanReadableLabel,
                    'label' => $humanReadableLabel,
                    'order' => $processedElements,
                    'elementable_type' => $type,
                ];

                $elementData['properties'] = [
                    'original_name' => $technicalName,
                    'imported' => true,
                    'import_source' => 'template',
                ];

                $formElement = null;

                if ($type === \App\Models\FormBuilding\SelectInputFormElement::class) {
                    $selectModel = \App\Models\FormBuilding\SelectInputFormElement::create($attributes);
                    $elementData['elementable_id'] = $selectModel->id;
                    $formElement = FormElement::create($elementData);
                    foreach ($options as $idx => $opt) {
                        $optionData = [
                            'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                            'order' => $opt['order'] ?? ($idx + 1),
                            'description' => $opt['description'] ?? null,
                        ];
                        \App\Models\FormBuilding\SelectOptionFormElement::createForSelect($selectModel, $optionData);
                    }
                } elseif ($type === \App\Models\FormBuilding\RadioInputFormElement::class) {
                    $radioModel = \App\Models\FormBuilding\RadioInputFormElement::create($attributes);
                    $elementData['elementable_id'] = $radioModel->id;
                    $formElement = FormElement::create($elementData);
                    foreach ($options as $idx => $opt) {
                        $optionData = [
                            'label' => $opt['label'] ?? $opt['text'] ?? $opt['name'] ?? $opt['value'] ?? '',
                            'order' => $opt['order'] ?? ($idx + 1),
                            'description' => $opt['description'] ?? null,
                        ];
                        \App\Models\FormBuilding\SelectOptionFormElement::createForRadio($radioModel, $optionData);
                    }
                } elseif ($type === \App\Models\FormBuilding\ContainerFormElement::class) {
                    $containerModel = \App\Models\FormBuilding\ContainerFormElement::create($attributes);
                    $elementData['elementable_id'] = $containerModel->id;
                    $formElement = FormElement::create($elementData);
                } elseif ($type === \App\Models\FormBuilding\TextInfoFormElement::class) {
                    $textInfoAttributes = [];
                    foreach ($attributes as $key => $value) {
                        if ($key === 'content') {
                            $textInfoAttributes[$key] = $value;
                        }
                    }
                    if (!isset($textInfoAttributes['content'])) {
                        $textInfoAttributes['content'] = '';
                    }
                    $textInfoModel = \App\Models\FormBuilding\TextInfoFormElement::create($textInfoAttributes);
                    $elementData['elementable_id'] = $textInfoModel->id;
                    $formElement = FormElement::create($elementData);
                } else {
                    if (method_exists($type, 'create')) {
                        $elementableModel = $type::create($attributes);
                        $elementData['elementable_id'] = $elementableModel->id;
                    }
                    $formElement = FormElement::create($elementData);
                }

                if ($formElement && $dataBindingInfo) {
                    $this->createDataBinding($formElement, $dataBindingInfo, $formVersion);
                }

                if ($formElement && (
                    ($type === \App\Models\FormBuilding\ContainerFormElement::class && !empty($element['elements']) && is_array($element['elements'])) ||
                    (!empty($element['children']) && is_array($element['children']))
                )) {
                    $childElements = $element['elements'] ?? $element['children'];
                    $childInRepeatable = $inRepeatableContainer || $isRepeatableContainer;
                    $childInPlusContainer = $inPlusContainer || $isPlusMinusElement;
                    $processedElements = $this->importElementsRecursive($childElements, $formElement->id, $formVersion, $processedElements, $totalElements, $childInRepeatable, $childInPlusContainer);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to import individual element', [
                    'element' => $element,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processedElements;
    }

    /**
     * Extract data binding information from element
     */
    private function extractDataBindingInfo(array $element): ?array
    {
        $dataBindingInfo = null;

        // Handle dataBinding object structure
        if (isset($element['dataBinding']) && is_array($element['dataBinding'])) {
            $dataBinding = $element['dataBinding'];

            if (isset($dataBinding['dataBindingPath'])) {
                $dataBindingInfo = [
                    'path' => $dataBinding['dataBindingPath'],
                    'type' => $dataBinding['dataBindingType'] ?? 'jsonpath'
                ];
            }
        }
        // Handle direct dataBinding string (legacy support)
        elseif (isset($element['dataBinding']) && is_string($element['dataBinding'])) {
            $dataBindingInfo = [
                'path' => $element['dataBinding'],
                'type' => 'jsonpath'
            ];
        }
        // Handle binding_ref (alternative format)
        elseif (isset($element['binding_ref']) && is_string($element['binding_ref'])) {
            $dataBindingInfo = [
                'path' => $element['binding_ref'],
                'type' => 'jsonpath'
            ];
        }

        return $dataBindingInfo;
    }

    /**
     * Create data binding for the form element
     */
    private function createDataBinding($formElement, array $dataBindingInfo, $formVersion): void
    {
        try {
            $formDataSource = $formVersion->formDataSources()->first();

            if (!$formDataSource) {
                $formDataSource = \App\Models\FormMetadata\FormDataSource::firstOrCreate([
                    'name' => 'Imported Data Source',
                    'type' => 'json',
                ], [
                    'description' => 'Auto-created data source for imported form elements',
                    'endpoint' => null,
                    'params' => null,
                    'body' => null,
                    'headers' => null,
                    'host' => null,
                ]);

                $formVersion->formDataSources()->attach($formDataSource->id, ['order' => 1]);
            }
            \App\Models\FormBuilding\FormElementDataBinding::create([
                'form_element_id' => $formElement->id,
                'form_data_source_id' => $formDataSource->id,
                'path' => $dataBindingInfo['path'],
                'order' => 1,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create data binding for imported element', [
                'element_id' => $formElement->id,
                'data_binding_info' => $dataBindingInfo,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function resolveElementableType(string $elementType): ?string
    {
        $map = [
            'TextInputFormElements' => \App\Models\FormBuilding\TextInputFormElement::class,
            'TextareaInputFormElements' => \App\Models\FormBuilding\TextareaInputFormElement::class,
            'TextInfoFormElements' => \App\Models\FormBuilding\TextInfoFormElement::class,
            'DateSelectInputFormElements' => \App\Models\FormBuilding\DateSelectInputFormElement::class,
            'CheckboxInputFormElements' => \App\Models\FormBuilding\CheckboxInputFormElement::class,
            'SelectInputFormElements' => \App\Models\FormBuilding\SelectInputFormElement::class,
            'RadioInputFormElements' => \App\Models\FormBuilding\RadioInputFormElement::class,
            'NumberInputFormElements' => \App\Models\FormBuilding\NumberInputFormElement::class,
            'ButtonInputFormElements' => \App\Models\FormBuilding\ButtonInputFormElement::class,
            'HTMLFormElements' => \App\Models\FormBuilding\HTMLFormElement::class,
            'ContainerFormElements' => \App\Models\FormBuilding\ContainerFormElement::class,
        ];

        if (isset($map[$elementType])) {
            return $map[$elementType];
        }

        $available = FormElement::getAvailableElementTypes();
        foreach ($available as $class => $label) {
            if ($class === $elementType || class_basename($class) === $elementType) {
                return $class;
            }
        }
        return null;
    }

    private function extractElementAttributes(array $element): array
    {
        $exclude = ['elements', 'children', 'token', 'parentId', 'elementType', 'type'];
        $attributes = [];
        foreach ($element as $key => $value) {
            if (!in_array($key, $exclude, true)) {
                $attributes[$key] = $value;
            }
        }

        if (isset($element['repeats'])) {
            $attributes['is_repeatable'] = (bool)$element['repeats'];
        }

        if (isset($element['minRepeats'])) {
            $attributes['min_repeats'] = (int)$element['minRepeats'];
        }

        if (isset($element['maxRepeats'])) {
            $attributes['max_repeats'] = (int)$element['maxRepeats'];
        }

        $elementType = $element['elementType'] ?? $element['type'] ?? '';

        if ($elementType === 'TextInfoFormElements' && isset($element['content'])) {
            $attributes['content'] = $element['content'];
        }

        if (isset($element['listItems'])) {
            $attributes['listItems'] = $element['listItems'];
        }
        if (isset($element['dataBinding'])) {
            $attributes['dataBinding'] = $element['dataBinding'];
        }
        if (isset($element['dataFormat'])) {
            $attributes['dataFormat'] = $element['dataFormat'];
        }
        return $attributes;
    }

    /**
     * Determine if the given element is a repeatable container.
     */
    private function isRepeatableContainer(array $element): bool
    {
        if (
            (isset($element['repeats']) && $element['repeats']) ||
            (isset($element['is_repeatable']) && $element['is_repeatable'])
        ) {
            return true;
        }
        return false;
    }

    /**
     * Determine if the given type is a text field element.
     */
    private function isTextField($type): bool
    {
        $textFieldTypes = [
            \App\Models\FormBuilding\TextInfoFormElement::class,
        ];
        return in_array($type, $textFieldTypes, true);
    }
}
