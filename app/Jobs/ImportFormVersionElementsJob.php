<?php

namespace App\Jobs;

use App\Models\FormBuilding\FormVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\FormBuilding\FormElement;
use App\Events\FormVersionUpdateEvent;
use App\Models\FormBuilding\ButtonInputFormElement;
use App\Models\FormBuilding\CheckboxGroupFormElement;
use App\Models\FormBuilding\CheckboxInputFormElement;
use App\Models\FormBuilding\ContainerFormElement;
use App\Models\FormBuilding\CurrencyInputFormElement;
use App\Models\FormBuilding\DateSelectInputFormElement;
use App\Models\FormBuilding\FormElementDataBinding;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\HTMLFormElement;
use App\Models\FormBuilding\NumberInputFormElement;
use App\Models\FormBuilding\RadioInputFormElement;
use App\Models\FormBuilding\SelectInputFormElement;
use App\Models\FormBuilding\SelectOptionFormElement;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\TextareaInputFormElement;
use App\Models\FormBuilding\TextInfoFormElement;
use App\Models\FormBuilding\TextInputFormElement;
use App\Models\FormMetadata\FormDataSource;

use Filament\Notifications\Notification;

class ImportFormVersionElementsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected $formVersionId;
    protected $schemaContent;
    protected $cacheKey;
    protected $userId;

    private $defaultDataSourceError = false;

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

            // Normalize format
            $normalizedSchema = $this->normalizeSchema($parsed);

            // Process data sources,  javascript, and stylesheets
            $this->processDataSources($normalizedSchema, $formVersion);
            $this->processJavaScript($normalizedSchema, $formVersion);
            $this->processStyleSheets($normalizedSchema, $formVersion);
            $elements = $normalizedSchema['elements'] ?? [];

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

            // Check for template conflicts and send database notification
            $templateConflicts = Cache::get($this->cacheKey . '_template_conflicts', []);
            if (!empty($templateConflicts)) {
                $user = \App\Models\User::find($this->userId);
                if ($user) {
                    $filenames = array_column($templateConflicts, 'filename');
                    $conflictList = implode(', ', $filenames);
                    $count = count($templateConflicts);
                    // Construct action buttons for notification
                    $actions = [];
                    foreach ($templateConflicts as $conflict) {
                        // Choose the route based on the template type
                        $routeName = $conflict['type'] === 'stylesheet'
                            ? 'filament.forms.resources.style-sheets.view'
                            : 'filament.forms.resources.form-scripts.view';

                        $actions[] = \Filament\Notifications\Actions\Action::make('view_template_' . $conflict['id'])
                            ->button()
                            ->label("View '{$conflict['filename']}'")
                            ->url(route($routeName, ['record' => $conflict['id']]));
                    }

                    Notification::make()
                        ->title('Template Conflicts Detected')
                        ->warning()
                        ->body(
                            "During import of {$formVersion->form->form_id} version {$formVersion->version_number}, {$count} template(s) already existed with different content. "
                            . "The existing versions were kept: {$conflictList}"
                        )
                        ->actions($actions)
                        ->sendToDatabase($user);
                }
            }

            FormVersionUpdateEvent::dispatch(
                $formVersion->id,
                $formVersion->form_id,
                $formVersion->version_number,
                null,
                'components',
                false
            );
        } catch (\Throwable $e) {
            Cache::put($this->cacheKey . '_status', 'error', 3600);
            Cache::put($this->cacheKey . '_error', $e->getMessage(), 3600);
        }
    }

    /**
     * Normalize different schema formats into a consistent structure
     */
    private function normalizeSchema(array $parsed): array
    {
        if (!isset($parsed['formversion'])) {
            throw new \Exception("Only 'formversion' format is supported");
        }

        $formVersion = $parsed['formversion'];

        return [
            'elements' => $formVersion['elements'] ?? [],
            'dataSources' => $formVersion['dataSources'] ?? [],
            'javascript' => $this->normalizeCodeAssets($formVersion['scripts'] ?? []),
            'stylesheets' => $this->normalizeCodeAssets($formVersion['styles'] ?? []),
        ];
    }

    /**
     * Normalize legacy boolean states to the new string enum states for visibility, required, and read-only fields.
     * - true / 1 / '1' -> 'always'
     * - false / 0 / '0' / null -> 'never'
     * - existing strings ('always', 'icm', 'portal', 'never') are returned as-is
     */
    private function normalizeLegacyState($value): string
    {
        if ($value === true || $value === 1 || $value === '1') {
            return 'always';
        }

        if ($value === false || $value === 0 || $value === '0' || $value === null) {
            return 'never';
        }

        return (string) $value;
    }

    /**
     * Normalize code assets (scripts/stylesheets) into a standard structure.
     * Transforms the JSON array format into a structure that matches our storage model:
     * - Web/PDF assets are concatenated into single strings
     * - Script templates remain as individual objects with filename and content
     */
    private function normalizeCodeAssets(array $assets): array
    {
        $normalized = [];

        foreach ($assets as $asset) {
            $type = $asset['type'] ?? 'web';
            $content = $asset['content'] ?? '';
            $filename = $asset['filename'] ?? null;

            if ($type === 'template') {
                // Templates need to remain as individual objects for separate file creation
                if ($filename && $filename !== '') {
                    if (!array_key_exists($type, $normalized)) {
                        $normalized[$type] = [];
                    }
                    $normalized[$type][] = [
                        'filename' => $filename,
                        'content' => $content,
                    ];
                }
            } else {
                // Web/PDF assets get concatenated into a single string
                if ($content !== '') {
                    $normalized[$type] = ($normalized[$type] ?? '');
                    $normalized[$type] .= ($normalized[$type] ? "\n" : "") . $content;
                }
            }
        }

        return $normalized;
    }

    /**
     * Extract JavaScript from formversion format
     */
    private function extractJavaScriptFromFormversion(array $formversion): array
    {
        $javascript = [];

        // Check for scripts array in formversion format
        if (!empty($formversion['scripts']) && is_array($formversion['scripts'])) {
            foreach ($formversion['scripts'] as $script) {
                $type = $script['type'] ?? 'web';
                $content = $script['content'] ?? '';
                if ($type === 'template') {
                    // Pass the template's filename as content so it can be attached
                    $content = $script['filename'];
                }
                if ($content !== '') {
                    if ($type === 'template') {
                        if (!array_key_exists($type, $javascript)) {
                            $javascript[$type] = [];
                        }
                        array_push($javascript[$type], $content);
                    } else {
                        // concatenate if multiple blocks of the same type exist
                        $javascript[$type] = ($javascript[$type] ?? '');
                        $javascript[$type] .= ($javascript[$type] ? "\n" : "") . $content;
                    }
                }
            }
        }

        return $javascript;
    }

    /**
     * Extract stylesheets from formversion format
     */
    private function extractStyleSheetsFromFormversion(array $formversion): array
    {
        $stylesheets = [];

        // Check for styles array in formversion format
        if (!empty($formversion['styles']) && is_array($formversion['styles'])) {
            foreach ($formversion['styles'] as $stylesheet) {
                $type = $stylesheet['type'] ?? 'web';
                $content = $stylesheet['content'] ?? '';
                if ($type === 'template') {
                    // Pass the template's filename as content so it can be attached
                    $content = $stylesheet['filename'];
                }
                if ($content !== '') {
                    if ($type === 'template') {
                        if (!array_key_exists($type, $stylesheets)) {
                            $stylesheets[$type] = [];
                        }
                        array_push($stylesheets[$type], $content);
                    } else {
                        // concatenate if multiple blocks of the same type exist
                        $stylesheets[$type] = ($stylesheets[$type] ?? '');
                        $stylesheets[$type] .= ($stylesheets[$type] ? "\n" : "") . $content;
                    }
                }
            }
        }
        return $stylesheets;
    }

    /**
     * Parse JavaScript content to extract individual sections
     */
    private function parseJavaScriptSections(string $content): array
    {
        $sections = [];

        // Split by section comments (// Section: sectionName)
        $lines = explode("\n", $content);
        $currentSection = null;
        $currentCode = [];

        foreach ($lines as $line) {
            // Check if this is a section header
            if (preg_match('/\/\/ Section: (.+)/', trim($line), $matches)) {
                // Save previous section if exists
                if ($currentSection && !empty($currentCode)) {
                    $sections[$currentSection] = implode("\n", $currentCode);
                }

                // Start new section
                $currentSection = trim($matches[1]);
                $currentCode = [];
            } elseif ($currentSection && trim($line) !== '') {
                // Add line to current section (skip empty lines at start)
                $currentCode[] = $line;
            }
        }

        // Save the last section
        if ($currentSection && !empty($currentCode)) {
            $sections[$currentSection] = implode("\n", $currentCode);
        }

        return $sections;
    }

    /**
     * Process data sources from the normalized schema
     */
    private function processDataSources(array $normalizedSchema, $formVersion): void
    {
        $dataSources = $normalizedSchema['dataSources'] ?? [];

        if (!$dataSources || !is_array($dataSources)) {
            return;
        }

        // Clear existing data source associations
        $formVersion->formDataSources()->detach();

        foreach ($dataSources as $index => $dataSourceData) {
            try {
                // Handle different field name formats
                $name = $dataSourceData['name'] ?? 'Imported Data Source ' . ($index + 1);
                $type = $dataSourceData['type'] ?? 'json';
                $description = $dataSourceData['description'] ?? 'Imported from template';
                $endpoint = $dataSourceData['endpoint'] ?? null;
                $params = isset($dataSourceData['params']) ?
                    (is_string($dataSourceData['params']) ? $dataSourceData['params'] : json_encode($dataSourceData['params'])) : null;
                $body = isset($dataSourceData['body']) ?
                    (is_string($dataSourceData['body']) ? $dataSourceData['body'] : json_encode($dataSourceData['body'])) : null;
                $headers = isset($dataSourceData['headers']) ?
                    (is_string($dataSourceData['headers']) ? $dataSourceData['headers'] : json_encode($dataSourceData['headers'])) : null;
                $host = $dataSourceData['host'] ?? null;

                // Create or find the data source
                $dataSource = FormDataSource::firstOrCreate([
                    'name' => $name,
                    'type' => $type,
                ], [
                    'description' => $description,
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'body' => $body,
                    'headers' => $headers,
                    'host' => $host,
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
     * Process JavaScript from the normalized schema. Handles form scripts and templates
     */
    private function processJavaScript(array $normalizedSchema, $formVersion): void
    {
        $javascript = $normalizedSchema['javascript'] ?? [];
        if (empty($javascript))
            return;

        foreach (['web', 'pdf', 'template'] as $type) {
            if (!isset($javascript[$type]))
                continue;

            if ($type === 'template') {
                foreach ($javascript[$type] as $templateData) {
                    $this->processTemplateScript($formVersion, $templateData);
                }
            } else {
                FormScript::createFormScript($formVersion, trim($javascript[$type]), $type);
            }
        }
    }

    /**
     * Process stylesheets from the normalized schema. Handles form styles and templates
     */
    private function processStyleSheets(array $normalizedSchema, $formVersion): void
    {
        $stylesheets = $normalizedSchema['stylesheets'] ?? [];
        if (empty($stylesheets))
            return;

        foreach (['web', 'pdf', 'template'] as $type) {
            if (!isset($stylesheets[$type]))
                continue;

            if ($type === 'template') {
                foreach ($stylesheets[$type] as $templateData) {
                    $this->processTemplateStyleSheet($formVersion, $templateData);
                }
            } else {
                StyleSheet::createStyleSheet($formVersion, trim($stylesheets[$type]), $type);
            }
        }
    }

    /**
     * Process a template script as a transaction. 
     * Includes conflict resolution for when records exist with the same filename but different content.
     */
    private function processTemplateScript($formVersion, array $templateData): void
    {
        $filename = $templateData['filename'];
        $incomingContent = $templateData['content'] ?? '';

        DB::transaction(function () use ($formVersion, $filename, $incomingContent) {
            $existing = FormScript::where('filename', $filename)->where('type', 'template')->first();

            if ($existing) {
                // Read content from disk to compare
                if ($existing->getJsContent() === $incomingContent) {
                    // Content matches - use existing template
                    $this->syncTemplate('script', $formVersion, $existing->id);
                    return;
                }

                // Content differs - CONFLICT! Use existing but record the conflict
                $this->syncTemplate('script', $formVersion, $existing->id);
                $this->recordTemplateConflict($filename, $existing->id, 'script');

                Log::warning('Template script conflict detected during import', [
                    'filename' => $filename,
                    'existing_template_id' => $existing->id,
                    'form_version_id' => $formVersion->id,
                    'message' => 'Template exists with different content. Using existing template.'
                ]);
            } else {
                // Template doesn't exist - create it
                $new = FormScript::create(['filename' => $filename, 'type' => 'template']);

                // Save content to disk
                if (!$new->saveJsContent($incomingContent)) {
                    throw new \Exception('Failed to save template JS content to file');
                }

                $this->syncTemplate('script', $formVersion, $new->id);
            }
        });
    }

    /**
     * Process a template stylesheet as a transaction. 
     * Includes conflict resolution for when records exist with the same filename but different content.
     */
    private function processTemplateStyleSheet($formVersion, array $templateData): void
    {
        $filename = $templateData['filename'];
        $incomingContent = $templateData['content'] ?? '';

        DB::transaction(function () use ($formVersion, $filename, $incomingContent) {
            $existing = StyleSheet::where('filename', $filename)->where('type', 'template')->first();

            if ($existing) {
                // Read content from disk to compare
                if ($existing->getCssContent() === $incomingContent) {
                    // Content matches - use existing template
                    $this->syncTemplate('stylesheet', $formVersion, $existing->id);
                    return;
                }

                // Content differs - CONFLICT! Use existing but record the conflict
                $this->syncTemplate('stylesheet', $formVersion, $existing->id);
                $this->recordTemplateConflict($filename, $existing->id, 'stylesheet');

                Log::warning('Template stylesheet conflict detected during import', [
                    'filename' => $filename,
                    'existing_template_id' => $existing->id,
                    'form_version_id' => $formVersion->id,
                    'message' => 'Template exists with different content. Using existing template.'
                ]);
            } else {
                // Template doesn't exist - create it
                $new = StyleSheet::create(['filename' => $filename, 'type' => 'template']);

                // Save content to disk
                if (!$new->saveCssContent($incomingContent)) {
                    throw new \Exception('Failed to save template CSS content to file');
                }

                $this->syncTemplate('stylesheet', $formVersion, $new->id);
            }
        });
    }

    /**
     * Sync a template to the form version relationship
     * Encapsulates the mapping between template type and relationship method
     */
    private function syncTemplate(string $type, $formVersion, int $templateId): void
    {
        if ($type === 'script') {
            $formVersion->formScripts()->syncWithoutDetaching($templateId);
        } else {
            $formVersion->styleSheets()->syncWithoutDetaching($templateId);
        }
    }

    /**
     * Record script and stylesheet template conflicts for later notification
     */
    private function recordTemplateConflict(string $filename, int $existingTemplateId, string $type = 'script'): void
    {
        $conflicts = Cache::get($this->cacheKey . '_template_conflicts', []);
        $ids = array_column($conflicts, 'id');

        if (!in_array($existingTemplateId, $ids)) {
            $conflicts[] = [
                'filename' => $filename,
                'id' => $existingTemplateId,
                'type' => $type,
            ];
            Cache::put($this->cacheKey . '_template_conflicts', $conflicts, 3600);
        }
    }

    /**
     * Return child elements for any container/group regardless of key naming.
     */
    private function getChildElements(array $element): array
    {
        $kids = $element['elements']
            ?? $element['children']
            ?? $element['containerItems']
            ?? $element['fields']
            ?? [];

        return is_array($kids) ? $kids : [];
    }

    // Add method to count total elements for progress tracking
    protected function countElementsRecursive(array $elements): int
    {
        $count = 0;
        foreach ($elements as $element) {
            $count++;

            $kids = $this->getChildElements($element);
            if (!empty($kids)) {
                $count += $this->countElementsRecursive($kids);
            }
        }
        return $count;
    }

    /**
     * Check if element is a button type
     */
    private function isButtonElement($elementType): bool
    {
        return $elementType === ButtonInputFormElement::class ||
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
            ContainerFormElement::class,
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
        if ($resolvedType === ContainerFormElement::class) {
            return true;
        }

        // Check container type property
        if (isset($element['containerType'])) {
            return true;
        }

        return false;
    }


    /**
     * Extract options from different element formats
     */
    private function extractOptions(array $element): array
    {
        $options = [];

        // Format 1: formversion format with options array
        if (!empty($element['options']) && is_array($element['options'])) {

            foreach ($element['options'] as $index => $option) {
                if (is_array($option)) {
                    $optionData = [
                        'label' => $option['label'] ?? '',
                        'order' => $option['order'] ?? ($index + 1),
                        'description' => $option['description'] ?? null,
                    ];
                    $options[] = $optionData;
                } else {
                    $optionData = [
                        'label' => (string) $option,
                        'order' => $index + 1,
                        'description' => null,
                    ];
                    $options[] = $optionData;
                }
            }
        }
        // Format 2: listItems array
        elseif (!empty($element['listItems']) && is_array($element['listItems'])) {
            foreach ($element['listItems'] as $idx => $item) {
                if (is_array($item)) {
                    $options[] = [
                        'label' => $item['label'] ?? $item['text'] ?? $item['name'] ?? $item['value'] ?? '',
                        'order' => $item['order'] ?? ($idx + 1),
                        'description' => $item['description'] ?? null,
                    ];
                } else {
                    $options[] = [
                        'label' => isset($item['value']) ? $item['value'] : (string) $item,
                        'order' => $idx + 1,
                        'description' => null,
                    ];
                }
            }
        }
        // Format 3: attributes.options
        elseif (!empty($element['attributes']['options']) && is_array($element['attributes']['options'])) {
            foreach ($element['attributes']['options'] as $idx => $option) {
                if (is_array($option)) {
                    $options[] = [
                        'label' => $option['label'] ?? '',
                        'order' => $option['order'] ?? ($idx + 1),
                        'description' => $option['description'] ?? null,
                    ];
                }
            }
        }

        // Filter out options with empty labels
        $options = array_filter($options, function ($option) {
            return !empty(trim($option['label']));
        });

        // Re-index and ensure proper order
        $options = array_values($options);
        foreach ($options as $index => &$option) {
            if (!isset($option['order']) || $option['order'] <= 0) {
                $option['order'] = $index + 1;
            }
        }

        return $options;
    }

    /**
     * Create select options for SelectInputFormElement
     */
    private function createSelectOptions($selectModel, array $options): void
    {
        if (empty($options))
            return;

        foreach ($options as $index => $optionData) {
            if (empty($optionData['label']))
                continue; // Skip options without labels

            try {
                SelectOptionFormElement::createForSelect($selectModel, $optionData);
            } catch (\Exception $e) {
                Log::error('Failed to create select option', [
                    'option_data' => $optionData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create radio options for RadioInputFormElement
     */
    private function createRadioOptions($radioModel, array $options): void
    {

        if (empty($options))
            return;

        foreach ($options as $index => $optionData) {
            if (empty($optionData['label']))
                continue; // Skip options without labels
            try {
                SelectOptionFormElement::createForRadio($radioModel, $optionData);
            } catch (\Exception $e) {
                Log::error('Failed to create radio option', [
                    'option_data' => $optionData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create checkbox group options for CheckboxGroupFormElement
     */
    private function createCheckboxGroupOptions($checkboxGroupModel, array $options): void
    {
        if (empty($options))
            return;

        foreach ($options as $index => $optionData) {
            if (empty($optionData['label']))
                continue; // Skip options without labels

            try {
                SelectOptionFormElement::createForCheckboxGroup($checkboxGroupModel, $optionData);
            } catch (\Exception $e) {
                Log::error('Failed to create checkbox group option', [
                    'option_data' => $optionData,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
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
                            $childElements = $this->getChildElements($element);
                            if (!empty($childElements)) {
                                $processedElements = $this->importElementsRecursive(
                                    $childElements,
                                    $parentId,
                                    $formVersion,
                                    $processedElements,
                                    $totalElements,
                                    $inRepeatableContainer /* or $childInRepeatable when present */ ,
                                    $inPlusContainer      /* or $childInPlusContainer when present */
                                );
                            }
                            continue;
                        }
                    }
                }
                $type = $this->resolveElementableType($elementType);

                // Fallback for lowercase/short types
                if (!$type) {
                    $typeMap = [
                        'container' => ContainerFormElement::class,
                        'group' => ContainerFormElement::class,
                        'text-input' => TextInputFormElement::class,
                        'textarea' => TextareaInputFormElement::class,
                        'textarea-input' => TextareaInputFormElement::class,
                        'radio' => RadioInputFormElement::class,
                        'radio-input' => RadioInputFormElement::class,
                        'dropdown' => SelectInputFormElement::class,
                        'dropdown-input' => SelectInputFormElement::class,
                        'select' => SelectInputFormElement::class,
                        'select-input' => SelectInputFormElement::class,
                        'checkbox' => CheckboxInputFormElement::class,
                        'checkbox-input' => CheckboxInputFormElement::class,
                        'checkbox-group' => CheckboxGroupFormElement::class,
                        'checkbox-group-input' => CheckboxGroupFormElement::class,
                        'date' => DateSelectInputFormElement::class,
                        'date-select-input' => DateSelectInputFormElement::class,
                        'number' => NumberInputFormElement::class,
                        'number-input' => NumberInputFormElement::class,
                        'currency' => CurrencyInputFormElement::class,
                        'currency-input' => CurrencyInputFormElement::class,
                        'html' => HTMLFormElement::class,
                        'text-info' => TextInfoFormElement::class,
                        'button' => ButtonInputFormElement::class,
                        'button-input' => ButtonInputFormElement::class,
                    ];
                    if (isset($typeMap[$elementType])) {
                        $type = $typeMap[$elementType];
                    }
                }

                if (!$type)
                    continue;

                $isRepeatableContainer = false;
                if ($type === ContainerFormElement::class) {
                    $isRepeatableContainer = $this->isRepeatableContainer($element);
                }

                if ($inRepeatableContainer && $this->isTextField($type)) {
                    $childElements = $this->getChildElements($element);
                    if (!empty($childElements)) {
                        $processedElements = $this->importElementsRecursive(
                            $childElements,
                            $parentId,
                            $formVersion,
                            $processedElements,
                            $totalElements,
                            $inRepeatableContainer /* or $childInRepeatable when present */ ,
                            $inPlusContainer      /* or $childInPlusContainer when present */
                        );
                    }
                    continue;
                }

                $attributes = $this->extractElementAttributes($element);

                // Extract data binding information before creating the element
                $dataBindingInfo = $this->extractDataBindingInfo($element);

                // Extract options from different formats
                $options = $this->extractOptions($element);

                // Get the human-readable label for both name and label fields
                $humanReadableLabel = null;

                // Special handling for TextInfo elements - use content if it's short
                if ($type === TextInfoFormElement::class && isset($element['content'])) {
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

                // Extract reference ID and generated ID from UUID
                if ($element['uuid'] ?? false) {
                    // JSON v2
                    $parts = explode('-', $element['uuid']);
                    $referenceId = implode('-', array_slice($parts, 0, -5));
                    $uuid = implode('-', array_slice($parts, -5));
                    // Fallback for reference ID
                    if (empty($referenceId)) {
                        $referenceId = $humanReadableLabel;
                    }
                } else {
                    // ADZE templates
                    $uuid = $element['token'];
                    $referenceId = $element['name'];
                }

                $attributes += ['is_read_only' => false];

                $elementData = [
                    'form_version_id' => $formVersion->id,
                    'uuid' => $uuid,
                    'parent_id' => $parentId,
                    'name' => $humanReadableLabel,
                    'label' => $humanReadableLabel,
                    'order' => $processedElements,
                    'elementable_type' => $type,
                    'reference_id' => $referenceId,
                    'description' => $attributes['description'] ?? '',
                    'help_text' => $attributes['help_text'] ?? '',
                    'visible_web' => $this->normalizeLegacyState($attributes['visible_web'] ?? null),
                    'visible_pdf' => $this->normalizeLegacyState($attributes['visible_pdf'] ?? null),
                    'is_required' => $this->normalizeLegacyState($attributes['is_required'] ?? null),
                    'is_read_only' => $this->normalizeLegacyState($attributes['is_read_only'] ?? null),
                    'save_on_submit' => $attributes['save_on_submit'] ?? true,
                ];

                $elementData['properties'] = [
                    'original_name' => $technicalName,
                    'imported' => true,
                    'import_source' => 'template',
                ];

                $formElement = null;

                if ($type === ContainerFormElement::class) {
                    $containerModel = ContainerFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $containerModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === TextInputFormElement::class) {
                    $textInputModel = TextInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $textInputModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === TextareaInputFormElement::class) {
                    $textareModel = TextareaInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $textareModel->id;
                    $formElement = FormElement::create($elementData);
                } elseif ($type === TextInfoFormElement::class) {
                    $textInfoModel = TextInfoFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $textInfoModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === DateSelectInputFormElement::class) {
                    $dateSelectModel = DateSelectInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $dateSelectModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === CheckboxInputFormElement::class) {
                    $checkboxInputModel = CheckboxInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $checkboxInputModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === CheckboxGroupFormElement::class) {
                    $checkboxGroupModel = CheckboxGroupFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $checkboxGroupModel->id;
                    $formElement = FormElement::create($elementData);
                    $this->createCheckboxGroupOptions($checkboxGroupModel, $options);
                } else if ($type === SelectInputFormElement::class) {
                    $selectModel = SelectInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $selectModel->id;
                    $formElement = FormElement::create($elementData);
                    $this->createSelectOptions($selectModel, $options);
                } elseif ($type === RadioInputFormElement::class) {
                    $radioModel = RadioInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $radioModel->id;
                    $formElement = FormElement::create($elementData);
                    $this->createRadioOptions($radioModel, $options);
                } else if ($type === NumberInputFormElement::class) {
                    $numberInputModel = NumberInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $numberInputModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === CurrencyInputFormElement::class) {
                    $currencyInputModel = CurrencyInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $currencyInputModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === ButtonInputFormElement::class) {
                    $buttonModel = ButtonInputFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $buttonModel->id;
                    $formElement = FormElement::create($elementData);
                } else if ($type === HTMLFormElement::class) {
                    $htmlModel = HTMLFormElement::create($attributes['attributes']);
                    $elementData['elementable_id'] = $htmlModel->id;
                    $formElement = FormElement::create($elementData);
                } else {
                    if (method_exists($type, 'create')) {
                        $elementableModel = $type::create($attributes['attributes']);
                        $elementData['elementable_id'] = $elementableModel->id;
                    }
                    $formElement = FormElement::create($elementData);
                }


                if ($formElement) {
                    // Create data binding
                    if ($dataBindingInfo) {
                        $this->createDataBinding($formElement, $dataBindingInfo, $formVersion);
                    }

                    // Attach tags
                    if (isset($attributes['tags'])) {
                        if (!empty($attributes['tags'])) {
                            foreach ($attributes['tags'] as $id => $filename) {
                                $formElement->tags()->attach($id);
                            }
                        }
                    }

                    // Import child elements
                    $childElements = $this->getChildElements($element);
                    if (!empty($childElements)) {
                        $childInRepeatable = $inRepeatableContainer || $isRepeatableContainer;
                        $childInPlusContainer = $inPlusContainer || $isPlusMinusElement;

                        $processedElements = $this->importElementsRecursive(
                            $childElements,
                            $formElement->id,
                            $formVersion,
                            $processedElements,
                            $totalElements,
                            $childInRepeatable,
                            $childInPlusContainer
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to import individual element', [
                    'element' => $element['name'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if ($this->defaultDataSourceError) {
            Cache::put(
                $this->cacheKey . '_warning',
                "Failed to create the default data source. 
                Please reseed the form_data_sources table using the following command: sail artisan db:seed --class=FormDataSourceSeeder",
                3600
            );
        }

        return $processedElements;
    }

    /**
     * Extract data binding information from element (handles both formats)
     */
    private function extractDataBindingInfo(array $element): ?array
    {
        $dataBindingInfo = null;

        // Format 1: dataBinding object structure (HR0080R-truncated-revised.json)
        if (isset($element['dataBinding']) && is_array($element['dataBinding'])) {
            $dataBinding = $element['dataBinding'];

            if (isset($dataBinding['dataBindingPath'])) {
                $dataBindingInfo = [
                    'source' => $dataBinding['source'] ?? null,
                    'path' => $dataBinding['dataBindingPath'],
                    'type' => $dataBinding['dataBindingType'] ?? 'jsonpath'
                ];
            }
        }
        // Format 2: direct dataBinding string (legacy support)
        elseif (isset($element['dataBinding']) && is_string($element['dataBinding'])) {
            $dataBindingInfo = [
                'source' => $element['source'] ?? null, // Replace with actual field name
                'path' => $element['dataBinding'],
                'type' => 'jsonpath'
            ];
        }
        // Format 3: dataBindings array (formversion format)
        elseif (isset($element['dataBindings']) && is_array($element['dataBindings'])) {
            // Take the first data binding if multiple exist
            $firstBinding = reset($element['dataBindings']);
            if ($firstBinding && isset($firstBinding['path'])) {
                $dataBindingInfo = [
                    'source' => $firstBinding['source'] ?? null,
                    'path' => $firstBinding['path'],
                    'type' => 'jsonpath'
                ];
            }
        }
        // Format 4: binding_ref (alternative format)
        elseif (isset($element['binding_ref']) && is_string($element['binding_ref'])) {
            $dataBindingInfo = [
                'source' => $element['source'] ?? null, // Replace with actual field name
                'path' => $element['binding_ref'],
                'type' => 'jsonpath'
            ];
        }
        // Format 5: exporter uses 'databindings' (array, lowercase 'b')
        elseif (isset($element['databindings']) && is_array($element['databindings']) && !empty($element['databindings'])) {
            $first = reset($element['databindings']);
            if (is_array($first) && isset($first['path'])) {
                $dataBindingInfo = [
                    'source' => $first['source'] ?? null,
                    'path' => $first['path'],
                    'type' => $first['type'] ?? 'jsonpath',
                ];
            }
        }

        return $dataBindingInfo;
    }

    /**
     * Create data binding for the form element
     */
    private function createDataBinding($formElement, array $dataBindingInfo, $formVersion): void
    {
        try {
            DB::transaction(function () use ($formElement, $dataBindingInfo, $formVersion) {
                $formDataSourceName = $dataBindingInfo['source'];

                $formDataSource = $formVersion->formDataSources()
                    ->where('form_data_sources.name', $formDataSourceName)
                    ->first();

                if (!$formDataSource) {
                    $defaultDataSource = 'Imported Data Source';

                    // Specific catch for creating the default data source
                    try {
                        $formDataSource = FormDataSource::firstOrCreate([
                            'name' => $defaultDataSource,
                            'type' => 'json',
                        ], [
                            'description' => 'Auto-created data source for imported form elements',
                            'endpoint' => null,
                            'params' => null,
                            'body' => null,
                            'headers' => null,
                            'host' => null,
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to create the default data source "{source}". Please reseed the form_data_sources table'
                            . ' using the following command: sail artisan db:seed --class=FormDataSourceSeeder', [
                            'source' => $defaultDataSource,
                            'error' => $e->getMessage(),
                        ]);

                        $this->defaultDataSourceError = true;

                        throw $e; // ensure transaction rolls back
                    }

                    $formVersion->formDataSources()->syncWithoutDetaching($formDataSource->id, ['order' => 1]);
                }

                FormElementDataBinding::updateOrCreate([
                    'form_element_id' => $formElement->id,
                    'form_data_source_id' => $formDataSource->id,
                    'path' => $dataBindingInfo['path'],
                    'condition' => $dataBindingInfo['condition'] ?? null,
                    'order' => 1,
                ]);
            });
        } catch (\Exception $e) {
            Log::warning('Failed to create data binding for imported element.', [
                'element_id' => $formElement->id,
                'data_binding_info' => $dataBindingInfo,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function resolveElementableType(string $elementType): ?string
    {
        $map = [
            'TextInputFormElements' => TextInputFormElement::class,
            'TextareaInputFormElements' => TextareaInputFormElement::class,
            'TextInfoFormElements' => TextInfoFormElement::class,
            'DateSelectInputFormElements' => DateSelectInputFormElement::class,
            'CheckboxInputFormElements' => CheckboxInputFormElement::class,
            'CheckboxGroupFormElements' => CheckboxGroupFormElement::class,
            'SelectInputFormElements' => SelectInputFormElement::class,
            'RadioInputFormElements' => RadioInputFormElement::class,
            'NumberInputFormElements' => NumberInputFormElement::class,
            'CurrencyInputFormElements' => CurrencyInputFormElement::class,
            'ButtonInputFormElements' => ButtonInputFormElement::class,
            'HTMLFormElements' => HTMLFormElement::class,
            'ContainerFormElements' => ContainerFormElement::class,
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
        $exclude = [
            'elements',
            'children',
            'containerItems',
            'fields',
            'token',
            'parentId',
            'elementType',
            'type',
            'dataBinding',
            'dataBindings',
            'databindings',
            'scripts',
            'javascript',
            'pdfStyles',
            'webStyles',
            'options',
            'listItems'
        ];
        $attributes = [];

        foreach ($element as $key => $value) {
            if (!in_array($key, $exclude, true)) {
                $attributes[$key] = $value;
            }
        }

        // Handle both formats for repeatable containers
        if (isset($element['repeats'])) {
            $attributes['is_repeatable'] = (bool) $element['repeats'];
            $attributes['attributes']['is_repeatable'] = (bool) $element['repeats'];
            if (isset($element['attributes']['repeaterItemLabel'])) {
                $attributes['attributes']['repeater_item_label'] = $element['attributes']['repeaterItemLabel'];
            }
        } elseif (isset($element['attributes']['isRepeatable'])) {
            $attributes['is_repeatable'] = (bool) $element['attributes']['isRepeatable'];
            $attributes['attributes']['is_repeatable'] = (bool) $element['attributes']['isRepeatable'];
            if (isset($element['attributes']['repeaterItemLabel'])) {
                $attributes['attributes']['repeater_item_label'] = $element['attributes']['repeaterItemLabel'];
            }
        }
        // Handle min/max repeats
        if (isset($element['minRepeats'])) {
            $attributes['min_repeats'] = (int) $element['minRepeats'];
        } elseif (isset($element['min_repeats'])) {
            $attributes['min_repeats'] = (int) $element['min_repeats'];
        }

        if (isset($element['maxRepeats'])) {
            $attributes['max_repeats'] = (int) $element['maxRepeats'];
        } elseif (isset($element['max_repeats'])) {
            $attributes['max_repeats'] = (int) $element['max_repeats'];
        }

        // Handle container type mapping
        if (isset($element['containerType'])) {
            $attributes['container_type'] = $element['containerType'];
        } elseif (isset($element['attributes']['containerType'])) {
            $attributes['attributes']['container_type'] = $element['attributes']['containerType'];
        }

        // Handle collapsible properties
        if (isset($element['collapsible'])) {
            $attributes['collapsible'] = (bool) $element['collapsible'];
        }
        if (isset($element['collapsedByDefault'])) {
            $attributes['collapsed_by_default'] = (bool) $element['collapsedByDefault'];
        }

        $elementType = $element['elementType'] ?? $element['type'] ?? '';

        // For TextInfo elements, ensure content is properly mapped
        if ($elementType === 'TextInfoFormElements' && isset($element['content'])) {
            $attributes['content'] = $element['content'];
        }

        // For Button elements, ensure label is properly mapped
        if ($elementType === 'ButtonInputFormElements' && isset($element['label'])) {
            $attributes['attributes']['text'] = $element['label'];
        }

        // Handle options/list items (both formats)
        // if (isset($element['listItems'])) {
        //     $attributes['listItems'] = $element['listItems'];
        // } elseif (isset($element['options'])) {
        //     $attributes['options'] = $element['options'];
        // }

        // Handle default values
        if (isset($element['attributes']['value'])) {
            $attributes['attributes']['defaultValue'] = $element['attributes']['value'];
        }

        // Handle date format
        if (isset($element['dateFormat'])) {
            $attributes['dateFormat'] = \App\Models\FormBuilding\DateSelectInputFormElement::convertFromFlatpickrFormat($element['dateFormat']);
        } else if (isset($element['attributes']['dateFormat'])) {
            $attributes['attributes']['dateFormat'] = \App\Models\FormBuilding\DateSelectInputFormElement::convertFromFlatpickrFormat($element['attributes']['dateFormat']);
        }

        // Handle HTML content
        if (isset($element['htmlContent'])) {
            $attributes['html_content'] = $element['htmlContent'];
        } else if (isset($element['attributes']['htmlContent'])) {
            $attributes['attributes']['html_content'] = $element['attributes']['htmlContent'];
        }


        // Ensure $attributes['attributes] exists
        if (!isset($attributes['attributes'])) {
            $attributes['attributes'] = [];
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
            (isset($element['attributes']['isRepeatable']) && $element['attributes']['isRepeatable'])
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
