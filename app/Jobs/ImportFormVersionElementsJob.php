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

            $formVersion = FormVersion::findOrFail($this->formVersionId);
            $parsed = json_decode($this->schemaContent, true);

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
                Cache::put($this->cacheKey . '_status', 'error', 3600);
                Cache::put($this->cacheKey . '_error', 'No elements found in schema.', 3600);
                return;
            }

            // Chunk the elements for large imports
            $chunks = array_chunk($elements, 10); // adjust chunk size as needed
            foreach ($chunks as $i => $chunk) {
                // Use the job's own methods, not BuildFormVersion
                $this->importElementsRecursive($chunk, null, $formVersion);
                Cache::put($this->cacheKey . '_progress', ($i + 1) . '/' . count($chunks), 3600);
            }

            Cache::put($this->cacheKey . '_status', 'complete', 3600);
        } catch (\Throwable $e) {
            Log::error('ImportFormVersionElementsJob error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            Cache::put($this->cacheKey . '_status', 'error', 3600);
            Cache::put($this->cacheKey . '_error', $e->getMessage(), 3600);
        }
    }

    // Copied/refactored from BuildFormVersion, but static and receives $formVersion
    protected function importElementsRecursive(array $elements, $parentId, $formVersion)
    {
        foreach ($elements as $element) {
            // --- Robustly map type for both legacy and new schemas ---
            $elementType = $element['elementType'] ?? $element['type'] ?? '';
            $type = $this->resolveElementableType($elementType);

            // Fallback for lowercase/short types (e.g. "container", "text-input", "radio", "dropdown", "date")
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
                    'button' => \App\Models\FormBuilding\ButtonInputFormElement::class,
                    'html' => \App\Models\FormBuilding\HTMLFormElement::class,
                    // Add more as needed
                ];
                if (isset($typeMap[$elementType])) {
                    $type = $typeMap[$elementType];
                }
            }

            if (!$type) {
                Log::error('Unknown elementable_type', ['elementType' => $elementType, 'element' => $element]);
                continue;
            }

            $attributes = $this->extractElementAttributes($element);

            $options = [];
            if (!empty($element['listItems']) && is_array($element['listItems'])) {
                $options = $element['listItems'];
            } elseif (!empty($element['options']) && is_array($element['options'])) {
                $options = $element['options'];
            }

            $elementData = [
                'form_version_id' => $formVersion->id,
                'parent_id' => $parentId,
                'name' => $element['name'] ?? null,
                'label' => $element['label'] ?? null,
                'order' => 0,
                'elementable_type' => $type,
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
            } else {
                if (method_exists($type, 'create')) {
                    $elementableModel = $type::create($attributes);
                    $elementData['elementable_id'] = $elementableModel->id;
                }
                $formElement = FormElement::create($elementData);
            }

            // Recursively import children for containers (support both 'elements' and 'children')
            if (
                ($type === \App\Models\FormBuilding\ContainerFormElement::class)
                && !empty($element['elements']) && is_array($element['elements'])
            ) {
                $this->importElementsRecursive($element['elements'], $formElement->id, $formVersion);
            } elseif (!empty($element['children']) && is_array($element['children'])) {
                $this->importElementsRecursive($element['children'], $formElement->id, $formVersion);
            }
        }
    }

    // Make sure these helpers are present and used instead of calling BuildFormVersion
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
}
