<?php

namespace App\Filament\Forms\Helpers;

use App\Models\FormField;
use App\Filament\Forms\Helpers\SchemaParser;
use Illuminate\Support\HtmlString;

class SchemaFormatter
{
    /**
     * Get all mapping options for all fields (static, for reuse).
     * Memory optimized version that loads only essential data.
     *
     * @param bool $lightweight Whether to use lightweight format (text only) or rich HTML format
     * @return array Options array for field mapping
     */
    public static function getAllMappingOptions(bool $lightweight = true): array
    {
        // Use minimal eager loading to reduce memory usage
        $allFields = \App\Models\FormField::select(['id', 'name', 'label'])
            ->with(['dataType:id,name'])
            ->get();

        $options = [
            'new' => 'Create New Field',
            'skip' => 'Skip This Field',
        ];

        $sortedFields = $allFields->sortBy('label');
        foreach ($sortedFields as $field) {
            $id = (string) $field->id;
            $dataType = $field->dataType->name ?? 'unknown';

            $typeIcon = match ($dataType) {
                'text' => '✏️',
                'number' => '🔢',
                'email' => '📧',
                'password' => '🔒',
                'date' => '📅',
                'datetime' => '🕒',
                'checkbox' => '✅',
                'radio' => '🔘',
                'select' => '🔽',
                'multiselect' => '📋',
                'textarea' => '📝',
                'tel' => '📞',
                'file' => '📎',
                'image' => '🖼️',
                'url' => '🔗',
                'hidden' => '👁️',
                'container' => '📦',
                default => '📄'
            };

            if ($lightweight) {
                // Simple text-based format - much more memory efficient
                $options[$id] = "#$field->id: {$field->label} ({$field->name}) $typeIcon";
            } else {
                // Rich HTML format - looks nicer but uses more memory
                $optionLabel = "<span style='display:flex;align-items:center;'>" .
                    "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                    "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                    "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                    "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                    "$typeIcon $dataType</span>" .
                    "</span>";
                $options[$id] = $optionLabel;
            }
        }

        $memoryFormat = $lightweight ? "lightweight" : "rich HTML";
        \Illuminate\Support\Facades\Log::debug("📋 Field mapping options created: " . count($options) . " options available ({$memoryFormat} format)");
        return $options;
    }

    /**
     * Generate schema for field mapping with preview option
     *
     * @param array $parsedSchema Parsed schema data
     * @param bool $showPreview Whether to enable field preview by default
     * @return array Field mapping schema components
     */
    public function getFieldMappingSchemaWithPreview(array $parsedSchema, bool $showPreview = true): array
    {
        $schema = [];
        $fields = [];
        $schemaParser = new SchemaParser();

        // Handle new format with data.elements
        if (isset($parsedSchema['data']) && isset($parsedSchema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['data']['elements']);
        }
        // Handle older format with fields directly
        elseif (isset($parsedSchema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($parsedSchema['fields']);
        }

        if (empty($fields)) {
            return [
                \Filament\Forms\Components\Placeholder::make('no_fields')
                    ->label('No fields found')
                    ->content('No fields were found in the schema or schema has not been parsed yet.')
            ];
        }

        // Get precomputed options for efficiency - use lightweight format for options list
        $precomputedOptions = self::getAllMappingOptions(true);

        // Note: Field previews are now always enabled for better UX
        // They are memory-optimized and only render when a field is selected

        foreach ($fields as $index => $field) {
            // Generate stable field ID based on the available field identifier
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $selectFieldName = "field_mapping_{$fieldId}";
            $previewFieldName = "preview_field_{$fieldId}";

            // Extract field properties from either format
            $label = $field['label'] ?? '';
            $name = $field['name'] ?? '';

            // Type determination based on format
            $type = '';
            if (isset($field['elementType'])) {
                $type = $field['elementType'];
                // Additional details for data format if available
                if (isset($field['dataFormat'])) {
                    $type .= " ({$field['dataFormat']})";
                }
            } else {
                $type = $field['type'] ?? 'text-input';
            }

            // Create schema components for this field
            $fieldComponents = [
                // Import field overview - always shown
                \Filament\Forms\Components\Placeholder::make("import_field_overview_{$fieldId}")
                    ->label('Import Field Overview')
                    ->content(new HtmlString($this->generateImportFieldOverview($field)))
                    ->columnSpanFull(),

                // ✅ Main Select Field - Enhanced with better search and responsiveness
                \Filament\Forms\Components\Select::make($selectFieldName)
                    ->label('Map to Existing Field or Create New')
                    ->searchable()
                    ->searchPrompt('Search by ID, name, or label...')
                    ->placeholder('Create new, skip, or select existing field')
                    ->default('new')
                    ->reactive()
                    ->live(onBlur: true) // Update on blur for better performance
                    ->preload()
                    ->allowHtml()
                    ->selectablePlaceholder(false)
                    ->helperText('Choose "Create New" to import this field, "Skip This Field" to exclude it from import, or search for an existing field by name, ID or label')
                    ->getSearchResultsUsing(function ($search) {
                        $options = [
                            'new' => '🆕 Create New Field',
                            'skip' => '⏭️ Skip This Field',
                        ];

                        $query = \App\Models\FormField::with('dataType:id,name');
                        if ($search) {
                            $query->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('label', 'like', "%{$search}%")
                                    ->orWhereRaw('CAST(id AS CHAR) LIKE ?', ["%{$search}%"]);
                            });
                        }

                        $fields = $query->orderBy('label')->orderBy('id')->limit(50)->get();
                        foreach ($fields as $field) {
                            $dataType = $field->dataType->name ?? 'unknown';
                            $typeIcon = match ($dataType) {
                                'text' => '✏️',
                                'number' => '🔢',
                                'email' => '📧',
                                'password' => '🔒',
                                'date' => '📅',
                                'datetime' => '🕒',
                                'checkbox' => '✅',
                                'radio' => '🔘',
                                'select' => '🔽',
                                'multiselect' => '📋',
                                'textarea' => '📝',
                                'tel' => '📞',
                                'file' => '📎',
                                'image' => '🖼️',
                                'url' => '🔗',
                                'hidden' => '👁️',
                                'container' => '📦',
                                default => '📄'
                            };

                            $optionLabel = "<span style='display:flex;align-items:center;'>" .
                                "<span style='color:#666;min-width:50px;'>#$field->id</span>" .
                                "<strong style='margin-right:8px;'>{$field->label}</strong> " .
                                "<span style='color:#777;margin-right:8px;'>({$field->name})</span>" .
                                "<span style='color:#444;background:#f3f4f6;padding:2px 6px;border-radius:4px;'>" .
                                "$typeIcon $dataType</span>" .
                                "</span>";
                            $options[(string)$field->id] = $optionLabel;
                        }
                        return $options;
                    })
                    ->options($precomputedOptions)
            ];

            $fieldComponents[] = \Filament\Forms\Components\Placeholder::make("action_summary_{$fieldId}")
                ->label('Import Action')
                ->content(function (callable $get) use ($fieldId) {
                    $selectedMapping = $get("field_mapping_{$fieldId}");

                    if (!$selectedMapping || $selectedMapping === 'new') {
                        return new HtmlString('<div class="p-2 bg-green-50 border border-green-200 rounded text-green-700 text-sm">✅ <strong>Will create new field</strong> - A new field will be created in the system</div>');
                    } elseif ($selectedMapping === 'skip') {
                        return new HtmlString('<div class="p-2 bg-gray-50 border border-gray-200 rounded text-gray-700 text-sm">⏭️ <strong>Skipping import of this field</strong> - This field will not be imported or created</div>');
                    } else {
                        return new HtmlString('<div class="p-2 bg-blue-50 border border-blue-200 rounded text-blue-700 text-sm">🔗 <strong>Will map to existing field</strong> - Import data will be mapped to field #' . htmlspecialchars($selectedMapping) . '</div>');
                    }
                })
                ->reactive()
                ->live()
                ->columnSpanFull();

            // Dynamic preview that updates when selection changes - always visible and reactive
            $fieldComponents[] = \Filament\Forms\Components\Placeholder::make($previewFieldName)
                ->label('Selection Preview')
                ->content(function (callable $get) use ($fieldId, $field) {
                    $selectedMapping = $get("field_mapping_{$fieldId}");

                    // Debug logging to help troubleshoot
                    \Illuminate\Support\Facades\Log::debug("Preview update for field {$fieldId}", [
                        'selected_mapping' => $selectedMapping,
                        'field_data' => $field
                    ]);

                    if (!$selectedMapping || $selectedMapping === '') {
                        return new HtmlString('<div class="text-gray-500 text-sm italic p-3 bg-gray-50 rounded border">👆 Select a field above to see preview</div>');
                    }

                    if ($selectedMapping === 'new') {
                        // Show preview indicating this will create a new field
                        $newFieldPreview = '<div class="p-3 bg-green-50 border border-green-200 rounded">' .
                            '<div class="flex items-center mb-2"><span class="text-green-600 font-semibold">🆕 Creating New Field</span></div>' .
                            '<div class="text-sm text-green-700 mb-3">This will create a new field based on the import data:</div>' .
                            $this->generateImportFieldOverview($field) .
                            '</div>';
                        return new HtmlString($newFieldPreview);
                    } elseif ($selectedMapping === 'skip') {
                        // Show simple message for skipped fields without detailed preview
                        $skipPreview = '<div class="p-3 bg-gray-50 border border-gray-200 rounded">' .
                            '<div class="flex items-center mb-2"><span class="text-gray-600 font-semibold">⏭️ Skipping Field</span></div>' .
                            '<div class="text-sm text-gray-700">This field will be skipped during import and will not be created in the system.</div>' .
                            '</div>';
                        return new HtmlString($skipPreview);
                    } else {
                        // Show preview of existing field when mapping to existing
                        try {
                            $existingField = \App\Models\FormField::with([
                                'dataType',
                                'formFieldValue',
                                'formFieldDateFormat',
                                'selectOptionInstances',
                                'formVersions'
                            ])->find((int)$selectedMapping);

                            if ($existingField) {
                                $mappingPreview = '<div class="p-3 bg-blue-50 border border-blue-200 rounded">' .
                                    '<div class="flex items-center mb-2"><span class="text-blue-600 font-semibold">🔗 Mapping to Existing Field</span></div>' .
                                    '<div class="text-sm text-blue-700 mb-3">Import data will be mapped to this existing field:</div>' .
                                    $this->generateExistingFieldOverview($existingField) .
                                    '</div>';
                                return new HtmlString($mappingPreview);
                            } else {
                                return new HtmlString('<div class="text-red-500 text-sm p-3 bg-red-50 border border-red-200 rounded">⚠️ Selected field not found (ID: ' . htmlspecialchars($selectedMapping) . ')</div>');
                            }
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Error loading field preview', [
                                'field_id' => $selectedMapping,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            return new HtmlString('<div class="text-red-500 text-sm p-3 bg-red-50 border border-red-200 rounded">⚠️ Error loading field: ' . htmlspecialchars($e->getMessage()) . '</div>');
                        }
                    }
                })
                ->reactive()
                ->live()
                ->columnSpanFull();

            // Summary action that will be taken


            // Add the field card to the schema
            $schema[] = \Filament\Forms\Components\Section::make("Field: {$label}")
                ->description("Configure mapping for field: {$name}")
                ->schema($fieldComponents)
                ->collapsible()
                ->collapsed(false);
        }

        return $schema;
    }

    /**
     * Generate a rich HTML overview of the imported field
     *
     * @param array $importField The field data to format
     * @return string HTML representation
     */
    public function generateImportFieldOverview(array $importField): string
    {
        // Extract essential field properties
        $name = $importField['name'] ?? $importField['uuid'] ?? '';

        // FIXED: Ensure we're getting the actual field label, not option values
        $label = '';
        if (isset($importField['label']) && is_string($importField['label'])) {
            $label = $importField['label'];
        } elseif (isset($importField['text']) && is_string($importField['text'])) {
            $label = $importField['text'];
        } elseif (isset($importField['title']) && is_string($importField['title'])) {
            $label = $importField['title'];
        }

        // Better type determination - include elementType for Adze format
        $type = $importField['type'] ?? $importField['dataType'] ?? $importField['data_type'] ?? $importField['elementType'] ?? '';

        // Handle repeating fields in either format (with better boolean handling)
        $repeating = false;
        if (isset($importField['repeats'])) {
            $repeating = $this->parseBooleanValue($importField['repeats']);
        } elseif (isset($importField['repeating'])) {
            $repeating = $this->parseBooleanValue($importField['repeating']);
        } elseif (isset($importField['is_repeating'])) {
            $repeating = $this->parseBooleanValue($importField['is_repeating']);
        }

        // Extract other common properties
        $helpText = $importField['help_text'] ?? $importField['helpText'] ?? '';
        $description = $importField['description'] ?? '';

        // Process validations
        $validations = [];
        if (isset($importField['validations'])) {
            $validations = $importField['validations'];
        } elseif (isset($importField['validation'])) {
            $validations = $importField['validation'];
        }
        $validationsText = is_array($validations)
            ? collect($validations)->map(function ($v, $k) {
                return is_array($v) ? "$k: " . json_encode($v) : "$k: $v";
            })->implode(', ')
            : $validations;

        // Extract options for select-type fields (legacy and Adze)
        $fieldOptions = '';
        if (isset($importField['options'])) {
            $fieldOptions = collect($importField['options'])->map(function ($opt) {
                return is_array($opt)
                    ? ($opt['label'] ?? $opt['text'] ?? $opt['value'] ?? '')
                    : $opt;
            })->implode(', ');
        } elseif (isset($importField['listItems']) && is_array($importField['listItems'])) {
            // Adze format: render as bullet list
            $fieldOptions = '<ul class="list-disc pl-5">';
            foreach ($importField['listItems'] as $item) {
                $optionLabel = $item['text'] ?? $item['label'] ?? $item['name'] ?? '';
                $value = $item['value'] ?? '';
                $fieldOptions .= '<li><span class="font-medium">' . htmlspecialchars($optionLabel) . '</span>';
                if ($value !== '') {
                    $fieldOptions .= ' <span class="text-gray-500">(' . htmlspecialchars($value) . ')</span>';
                }
                $fieldOptions .= '</li>';
            }
            $fieldOptions .= '</ul>';
        }

        // Build field details array with emojis for better visual scanning
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Name' => $name,
                'Type' => $type,
                'Label' => $label,
                'Repeating' => $repeating ? 'Yes' : 'No',
                'Visible' => isset($importField['isVisible']) ? ($this->parseBooleanValue($importField['isVisible']) ? 'Yes' : 'No') : 'Not specified',
                'Enabled' => isset($importField['isEnabled']) ? ($this->parseBooleanValue($importField['isEnabled']) ? 'Yes' : 'No') : 'Not specified',
                'Read Only' => isset($importField['isReadOnly']) ? ($this->parseBooleanValue($importField['isReadOnly']) ? 'Yes' : 'No') : 'Not specified',
            ],
            '📝 Content' => [
                'Help Text' => $helpText ?: 'None',
                'Description' => $description ?: 'None',
            ],
            '✓ Validation' => [
                'Rules' => $validationsText ?: 'None',
            ],
        ];

        // Add options section if applicable
        if ($fieldOptions) {
            $fieldDetails['🔽 Options'] = [
                'Options' => $fieldOptions,
                'Count' => isset($importField['options']) ? count($importField['options']) : (isset($importField['listItems']) ? count($importField['listItems']) : 0),
            ];
        }

        // Add additional properties section for anything else
        $additional = [];
        foreach ($importField as $key => $value) {
            // Skip keys we've already processed
            if (!in_array($key, [
                'name',
                'uuid',
                'label',
                'type',
                'dataType',
                'data_type',
                'repeats',
                'repeating',
                'is_repeating',
                'help_text',
                'helpText',
                'description',
                'validations',
                'validation',
                'options',
                'listItems',
                'token',
                'parentId',
                'elementType',
                'isVisible',
                'isEnabled',
                'isReadOnly',
            ])) {
                if (is_array($value)) {
                    $additional[$key] = json_encode($value);
                } elseif (!empty($value) || $value === '0' || $value === 0) {
                    $additional[$key] = is_string($value) ? $value : (string) $value;
                }
            }
        }

        if (!empty($additional)) {
            $fieldDetails['⚙️ Additional Properties'] = $additional;
        }

        // Generate HTML with collapsible sections for better organization
        return $this->generateHTMLFromFieldDetails($fieldDetails);
    }

    /**
     * Generate HTML overview of an existing field
     *
     * @param FormField $field The field model to format
     * @return string HTML representation
     */
    public function generateExistingFieldOverview(FormField $field): string
    {
        // Basic info
        $fieldDetails = [
            '🔑 Basic Info' => [
                'Field ID' => $field->id,
                'Name' => $field->name,
                'Type' => $field->dataType->name ?? 'unknown',
                'Label' => $field->label,
                'Created' => $field->created_at ? $field->created_at->format('Y-m-d') : '',
                'Updated' => $field->updated_at ? $field->updated_at->format('Y-m-d') : '',
            ],
            '📝 Content & Display' => [
                'Help Text' => $field->help_text ?: 'None',
                'Description' => $field->description ?: 'None',
                'Data Binding' => $field->data_binding ?: 'None',
                'Mask' => $field->mask ?: 'None',
            ],
            '✓ Validation' => [
                'Rules' => ($field->validations && $field->validations->count() > 0)
                    ? $field->validations->map(function ($validation) {
                        return "$validation->type: $validation->value";
                    })->join(', ')
                    : 'None',
            ],
            '🗂️ Organization' => [
                'Field Groups' => ($field->fieldGroups && $field->fieldGroups->count() > 0)
                    ? $field->fieldGroups->pluck('name')->join(', ')
                    : 'Not assigned to any groups',
            ],
            '🎨 Styling' => [
                'Web Styles' => ($field->webStyles && $field->webStyles->count() > 0)
                    ? $field->webStyles->pluck('name')->join(', ')
                    : 'No web styles applied',
                'PDF Styles' => ($field->pdfStyles && $field->pdfStyles->count() > 0)
                    ? $field->pdfStyles->pluck('name')->join(', ')
                    : 'No PDF styles applied',
            ],
        ];

        // Options for select/radio/checkbox
        if (in_array($field->dataType->name ?? '', ['select', 'multiselect', 'radio', 'checkbox'])) {
            $optionsHtml = '';
            if ($field->selectOptionInstances && $field->selectOptionInstances->count() > 0) {
                $optionsHtml = '<ul class="list-disc pl-5">';
                foreach ($field->selectOptionInstances as $opt) {
                    $optionsHtml .= '<li><span class="font-medium">' . htmlspecialchars($opt->label) . '</span>';
                    if ($opt->value !== '') {
                        $optionsHtml .= ' <span class="text-gray-500">(' . htmlspecialchars($opt->value) . ')</span>';
                    }
                    $optionsHtml .= '</li>';
                }
                $optionsHtml .= '</ul>';
            } else {
                $optionsHtml = 'No options defined';
            }
            $fieldDetails['🔽 Options'] = [
                'Options' => $optionsHtml,
                'Count' => $field->selectOptionInstances ? $field->selectOptionInstances->count() : 0,
            ];
        }

        // Date format
        if (in_array($field->dataType->name ?? '', ['date', 'datetime'])) {
            $fieldDetails['📅 Date Settings'] = [
                'Date Format' => $field->formFieldDateFormat ? ($field->formFieldDateFormat->format ?? 'Default') : 'Default system format',
            ];
        }

        // Default value
        $fieldDetails['⚙️ Default Settings'] = [
            'Default Value' => $field->formFieldValue ? ($field->formFieldValue->value ?? 'None') : 'None',
        ];

        // Usage
        $formsCount = $field->formVersions ? $field->formVersions->count() : 0;
        $usage = [
            'Used In' => $formsCount . ' form version(s)',
        ];
        if ($formsCount > 0) {
            $formNames = $field->formVersions->pluck('form_title')->unique()->take(3)->join(', ');
            if ($field->formVersions->count() > 3) {
                $formNames .= ' and ' . ($field->formVersions->count() - 3) . ' more';
            }
            $usage['Form Names'] = $formNames;
        }
        $fieldDetails['📊 Usage Statistics'] = $usage;

        // Action
        $fieldDetails['🔄 Action'] = [
            'Action' => 'Will use this existing field for mapping',
        ];

        // Generate HTML with the same structure as import fields
        return $this->generateHTMLFromFieldDetails($fieldDetails);
    }

    /**
     * Generate HTML from a field details array
     *
     * @param array $fieldDetails Structured field details array
     * @return string HTML markup
     */
    protected function generateHTMLFromFieldDetails(array $fieldDetails): string
    {
        $html = '<div class="space-y-4 p-2 overflow-auto max-h-64">';

        foreach ($fieldDetails as $section => $items) {
            $html .= '<div class="border rounded-md bg-gray-50">';
            $html .= '<div class="font-medium text-lg p-2 bg-gray-100 border-b">' . $section . '</div>';
            $html .= '<table class="min-w-full">';

            foreach ($items as $key => $value) {
                $html .= '<tr class="border-t border-gray-200">';
                $html .= '<td class="py-2 px-4 font-medium text-gray-700 w-1/3">' . htmlspecialchars($key) . '</td>';
                if ($key === 'Options') {
                    $html .= '<td class="py-2 px-4 text-gray-800">' . $value . '</td>'; // allow HTML for options
                } elseif (empty($value) && $value !== '0' && $value !== 0) {
                    $html .= '<td class="py-2 px-4 text-gray-500 italic">empty</td>';
                } else {
                    $html .= '<td class="py-2 px-4 text-gray-800">' . htmlspecialchars((string)$value) . '</td>';
                }
                $html .= '</tr>';
            }

            $html .= '</table>';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate a JSON preview of the parsed schema
     *
     * @param array $schema The parsed schema array
     * @param array $data Additional data for rendering
     * @return string JSON representation of the schema
     */
    public function getImportPreviewJson(array $schema, array $data = []): string
    {
        // Filter out sensitive or unnecessary information
        $filteredSchema = $this->filterSchemaForPreview($schema);

        // Format the schema as JSON with proper indentation for readability
        return json_encode($filteredSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Filter schema for preview to remove sensitive data and reduce size
     *
     * @param array $schema The complete schema
     * @return array Filtered schema
     */
    protected function filterSchemaForPreview(array $schema): array
    {
        // Create a deep copy to avoid modifying the original
        $filtered = $schema;

        // Extract fields from the schema using the same logic as field mapping
        $schemaParser = new SchemaParser();
        $fields = [];

        // Handle new format with data.elements
        if (isset($schema['data']) && isset($schema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($schema['data']['elements']);
            $filtered['fields'] = $fields; // Normalize to 'fields' for preview
            $filtered['format'] = 'adze-template';
        }
        // Handle older format with fields directly
        elseif (isset($schema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($schema['fields']);
            $filtered['fields'] = $fields;
            $filtered['format'] = 'legacy';
        }

        // Debug field order in preview
        $fieldNames = array_map(function ($field) {
            return $field['name'] ?? $field['label'] ?? $field['id'] ?? 'unknown';
        }, array_slice($fields, 0, 5));
        logger()->debug("🔍 Import preview field order - First 5: " . implode(', ', $fieldNames));

        // Check if we have fields and if it's an array we can display
        if (empty($fields) || !is_array($fields)) {
            return ['notice' => 'No fields found in schema or invalid format'];
        }

        // Keep only the essential information about each field
        foreach ($filtered['fields'] as $key => $field) {
            // Ensure it's an array we can work with
            if (!is_array($field)) continue;

            // Keep only essential field properties for preview
            $filtered['fields'][$key] = array_intersect_key($field, array_flip([
                'name',
                'type',
                'label',
                'id',
                'token',
                'uuid',
                'repeating',
                'repeats',
                'is_repeating',
                'data_type',
                'dataType',
                'elementType',
                'dataFormat',
                'description',
                'help_text',
                'helpText',
            ]));
        }

        // Only include basic form metadata
        $allowedKeys = ['title', 'description', 'version', 'type', 'fieldCount', 'format'];
        $filteredMeta = array_intersect_key($filtered, array_flip($allowedKeys));

        // Add field count explicitly if missing
        $fieldCount = count($filtered['fields'] ?? []);
        $filteredMeta['fieldCount'] = $fieldCount;

        // Merge metadata with filtered fields
        $result = $filteredMeta;
        $result['fields'] = array_values($filtered['fields']); // Reset keys for cleaner JSON

        return $result;
    }

    /**
     * Generate a JSON preview based on field mapping choices
     * Shows only fields that will actually be created, excluding skipped and mapped fields
     *
     * @param array $schema The parsed schema array
     * @param array $fieldMappings The user's field mapping choices
     * @param array $data Additional data for rendering
     * @return string JSON representation of fields that will be created
     */
    public function getImportPreviewWithMappings(array $schema, array $fieldMappings = [], array $data = []): string
    {
        // Extract fields from the schema
        $schemaParser = new SchemaParser();
        $fields = [];

        // Handle new format with data.elements
        if (isset($schema['data']) && isset($schema['data']['elements'])) {
            $fields = $schemaParser->extractFieldsFromSchema($schema['data']['elements']);
            $format = 'adze-template';
        }
        // Handle older format with fields directly
        elseif (isset($schema['fields'])) {
            $fields = $schemaParser->extractFieldsFromSchema($schema['fields']);
            $format = 'legacy';
        }

        if (empty($fields)) {
            return json_encode(['notice' => 'No fields found in schema'], JSON_PRETTY_PRINT);
        }

        // Filter fields based on mapping choices
        $fieldsToCreate = [];
        $fieldsMapped = [];
        $fieldsSkipped = [];

        foreach ($fields as $index => $field) {
            $fieldId = $field['token'] ?? $field['id'] ?? md5($field['name'] ?? "field_$index");
            $mappingKey = "field_mapping_{$fieldId}";
            $mapping = $fieldMappings[$mappingKey] ?? 'new'; // Default to 'new' if not set

            if ($mapping === 'skip') {
                $fieldsSkipped[] = [
                    'name' => $field['name'] ?? '',
                    'label' => $field['label'] ?? '',
                    'reason' => 'User chose to skip this field'
                ];
            } elseif ($mapping === 'new' || empty($mapping)) {
                // Will create new field - include in preview
                $fieldsToCreate[] = array_intersect_key($field, array_flip([
                    'name',
                    'type',
                    'label',
                    'id',
                    'token',
                    'uuid',
                    'repeating',
                    'repeats',
                    'is_repeating',
                    'data_type',
                    'dataType',
                    'elementType',
                    'dataFormat',
                    'description',
                    'help_text',
                    'helpText',
                ]));
            } else {
                // Will map to existing field
                $fieldsMapped[] = [
                    'name' => $field['name'] ?? '',
                    'label' => $field['label'] ?? '',
                    'mapped_to_field_id' => $mapping,
                    'action' => 'Map to existing field'
                ];
            }
        }

        // Build comprehensive preview
        $preview = [
            'title' => $schema['title'] ?? 'Imported Form',
            'format' => $format,
            'import_summary' => [
                'total_fields_in_schema' => count($fields),
                'new_fields_to_create' => count($fieldsToCreate),
                'fields_mapped_to_existing' => count($fieldsMapped),
                'fields_skipped' => count($fieldsSkipped)
            ]
        ];

        // Add helpful message
        if (count($fieldsToCreate) === 0 && count($fieldsMapped) > 0) {
            $preview['message'] = 'No new fields will be created. All fields are either mapped to existing fields or skipped.';
        } elseif (count($fieldsToCreate) > 0) {
            $preview['message'] = count($fieldsToCreate) . ' new field(s) will be created in the system.';
        }

        // Only include fields that will actually be created
        if (!empty($fieldsToCreate)) {
            $preview['new_fields'] = array_values($fieldsToCreate);
        }

        // Include mapping information if any
        if (!empty($fieldsMapped)) {
            $preview['mapped_fields'] = $fieldsMapped;
        }

        // Include skipped information if any
        if (!empty($fieldsSkipped)) {
            $preview['skipped_fields'] = $fieldsSkipped;
        }

        return json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Parse boolean values consistently across different input formats
     *
     * @param mixed $value The value to parse as boolean
     * @return bool True if the value represents true, false otherwise
     */
    protected function parseBooleanValue($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'true';
    }
}
