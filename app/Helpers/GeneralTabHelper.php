<?php

namespace App\Helpers;

use App\Models\FormBuilding\FormElement;
use App\Models\FormBuilding\FormElementTag;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms;

class GeneralTabHelper
{
    /**
     * Get the General tab schema for form elements
     *
     * @param string $mode The form mode: 'create', 'edit', or 'view'
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @param bool $includeTemplateSelector Whether to include the template selector (only for create mode)
     * @param callable|null $disabledCallback Callback to determine if fields should be disabled
     * @return array The schema array
     */
    public static function getGeneralTabSchema(
        string $mode = 'create',
        ?callable $shouldShowTooltipsCallback = null,
        bool $includeTemplateSelector = false,
        ?callable $disabledCallback = null
    ): array {
        $disabled = $mode === 'view';
        $isEdit = $mode === 'edit';
        $isCreate = $mode === 'create';

        $schema = [];

        // Template selector (only for create mode when explicitly requested)
        if ($includeTemplateSelector && $isCreate) {
            $templateField = Select::make('template_id')
                ->label('Start from template')
                ->placeholder('Select a template (optional)')
                ->options(function () {
                    $templates = FormElement::templates()
                        ->with('elementable')
                        ->get();

                    $availableTypes = FormElement::getAvailableElementTypes();
                    $groupedOptions = [];

                    foreach ($templates as $template) {
                        $elementType = $template->elementable_type;
                        $groupName = $availableTypes[$elementType] ?? class_basename($elementType);
                        $typeName = $availableTypes[$elementType] ?? class_basename($elementType);

                        if (!isset($groupedOptions[$groupName])) {
                            $groupedOptions[$groupName] = [];
                        }

                        $groupedOptions[$groupName][$template->id] = $template->name . ' (' . $typeName . ')';
                    }

                    // Sort groups alphabetically
                    ksort($groupedOptions);

                    return $groupedOptions;
                })
                ->live()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if (!$state) {
                        return;
                    }

                    // Load the template element with its relationships
                    $template = FormElement::with(['elementable', 'tags'])->find($state);

                    if (!$template) {
                        return;
                    }

                    // Prefill basic form element data
                    $set('name', $template->name);
                    $set('description', $template->description);
                    $set('help_text', $template->help_text);
                    $set('elementable_type', $template->elementable_type);
                    $set('is_required', $template->is_required);
                    $set('visible_web', $template->visible_web);
                    $set('visible_pdf', $template->visible_pdf);
                    $set('is_template', false); // New element should not be a template by default
    
                    // Prefill tags
                    if ($template->tags->isNotEmpty()) {
                        $set('tags', $template->tags->pluck('id')->toArray());
                    }

                    // Prefill elementable data if it exists
                    if ($template->elementable) {
                        $elementableData = $template->elementable->toArray();
                        // Remove timestamps and primary key
                        unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);
                        $set('elementable_data', $elementableData);
                    }
                })
                ->searchable()
                ->columnSpanFull();

            // Add tooltip if callback is provided
            if ($shouldShowTooltipsCallback) {
                $templateField = $templateField->when($shouldShowTooltipsCallback, function ($component) {
                    return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Select a template to start with pre-configured settings. For containers, this will also clone all child elements.');
                });
            }

            $schema[] = $templateField;
        }

        // Name field
        $nameField = TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->label('Element Name')
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add auto-generation logic for create mode
        if ($isCreate) {
            $nameField = $nameField
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    // Auto-generate reference_id if it's empty and we have a name
                    if (!empty($state) && empty($get('reference_id'))) {
                        $slug = \Illuminate\Support\Str::slug($state, '-');
                        $set('reference_id', $slug);
                    }
                });
        }

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $nameField = $nameField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Human friendly identifier to help you find and reference this element');
            });
        }

        $schema[] = $nameField;
        $schema[] = Hidden::make('id')->dehydrated(false);

        // Reference ID field - editable on create and edit
        $referenceIdField = TextInput::make('reference_id')
            ->label('Reference ID')
            ->rules(['alpha_dash'])
            ->live()
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()))
            ->suffixIcon(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                return ($lw && method_exists($lw, 'isFieldInvalid') && $lw->isFieldInvalid((int) $get('id'), 'reference_id'))
                    ? 'heroicon-o-exclamation-triangle' : null;
            })
            ->hint(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                return ($lw && method_exists($lw, 'isFieldInvalid') && $lw->isFieldInvalid((int) $get('id'), 'reference_id'))
                    ? 'Invalid: ' . $lw->invalidReason((int) $get('id'), 'reference_id') : null;
            })
            // make the “?” icon returns to normal when clean
            ->hintColor(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                return ($lw && method_exists($lw, 'isFieldInvalid') && $lw->isFieldInvalid((int) $get('id'), 'reference_id'))
                    ? 'danger' : 'gray';
            })
            ->extraAttributes(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                return ($lw && method_exists($lw, 'isFieldInvalid') && $lw->isFieldInvalid((int) $get('id'), 'reference_id'))
                    ? ['class' => 'ring-1 ring-danger-500 bg-danger-50/50'] : [];
            })
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set, $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if (!$lw)
                    return;

                $id = (int) $get('id');
                $value = is_string($state) ? trim($state) : '';

                if ($value !== '' && !preg_match('/^\d/', $value)) {
                    if (method_exists($lw, 'clearInvalidMarker'))
                        $lw->clearInvalidMarker($id, 'reference_id');
                } else {
                    if (method_exists($lw, 'markInvalid'))
                        $lw->markInvalid($id, 'reference_id', $value === '' ? 'empty' : 'starts with a number', $value);
                }

                // refresh builder highlights
                if (method_exists($lw, 'dispatch')) {
                    $lw->dispatch('ff-markers-updated', markers: $lw->invalidByElement ?? [])
                        ->to('form-element-tree-builder');
                }
            });

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $referenceIdField = $referenceIdField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Human-readable identifier to aid creating ICM data bindings');
            });
        }

        // Add suffix and actions for edit mode
        if ($isEdit) {
            $schema[] = Hidden::make('reference_id_locked');
            $referenceIdField = $referenceIdField
                ->prefixAction(
                    Action::make('toggleLock')
                        ->icon(fn($get) => $get('reference_id_locked') ? 'heroicon-s-lock-open' : 'heroicon-s-lock-closed')
                        ->tooltip(fn($get) => $get('reference_id_locked')
                            ? ''
                            : 'Click to unlock and edit the Reference ID. Changing this value may break ICM data bindings.')->action(function ($set, $get) {
                                $set('reference_id_locked', true);
                            })
                )
                ->disabled(fn($get) => !$get('reference_id_locked'))
                ->suffix(function ($get) {
                    return $get('uuid') ? $get('uuid') : '';
                })
                ->autocomplete(false)
                ->suffixAction(
                    Action::make('copy')
                        ->icon('heroicon-s-clipboard')
                        ->action(function ($livewire, $state, $get) {
                            $fullReference = FormElement::buildFullReferenceId($state, $get('uuid'));
                            $livewire->dispatch('copy-to-clipboard', text: $fullReference);
                        })
                )
                ->extraAttributes([
                    'x-data' => '{
                        copyToClipboard(text) {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(text).then(() => {
                                    $tooltip("Copied to clipboard", { timeout: 1500 });
                                }).catch(() => {
                                    $tooltip("Failed to copy", { timeout: 1500 });
                                });
                            } else {
                                const textArea = document.createElement("textarea");
                                textArea.value = text;
                                textArea.style.position = "fixed";
                                textArea.style.opacity = "0";
                                document.body.appendChild(textArea);
                                textArea.select();
                                try {
                                    document.execCommand("copy");
                                    $tooltip("Copied to clipboard", { timeout: 1500 });
                                } catch (err) {
                                    $tooltip("Failed to copy", { timeout: 1500 });
                                }
                                document.body.removeChild(textArea);
                            }
                        }
                    }',
                    'x-on:copy-to-clipboard.window' => 'copyToClipboard($event.detail.text)',
                ]);
        } elseif ($isCreate) {
            // Add regenerate action for create mode
            $referenceIdField = $referenceIdField->suffixAction(
                Action::make('regenerate_reference_id')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Regenerate from Element Name')
                    ->action(function (callable $set, callable $get) {
                        $name = $get('name');
                        if (!empty($name)) {
                            $slug = \Illuminate\Support\Str::slug($name, '-');
                            $set('reference_id', $slug);
                        }
                    })
            );
        } elseif ($mode === 'view') {
            // Add copy functionality for view mode
            $referenceIdField = $referenceIdField
                ->suffix(function ($get) {
                    return $get('uuid') ? $get('uuid') : '';
                })
                ->suffixAction(
                    Action::make('copy')
                        ->icon('heroicon-s-clipboard')
                        ->action(function ($livewire, $state, $get) {
                            $fullReference = FormElement::buildFullReferenceId($state, $get('uuid'));
                            $livewire->dispatch('copy-to-clipboard', text: $fullReference);
                        })
                )
                ->extraAttributes([
                    'x-data' => '{
                        copyToClipboard(text) {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(text).then(() => {
                                    $tooltip("Copied to clipboard", { timeout: 1500 });
                                }).catch(() => {
                                    $tooltip("Failed to copy", { timeout: 1500 });
                                });
                            } else {
                                const textArea = document.createElement("textarea");
                                textArea.value = text;
                                textArea.style.position = "fixed";
                                textArea.style.opacity = "0";
                                document.body.appendChild(textArea);
                                textArea.select();
                                try {
                                    document.execCommand("copy");
                                    $tooltip("Copied to clipboard", { timeout: 1500 });
                                } catch (err) {
                                    $tooltip("Failed to copy", { timeout: 1500 });
                                }
                                document.body.removeChild(textArea);
                            }
                        }
                    }',
                    'x-on:copy-to-clipboard.window' => 'copyToClipboard($event.detail.text)',
                ]);
        }

        $schema[] = $referenceIdField;

        // Element type field (different handling for edit vs create/view)
        if ($isEdit) {
            // For edit mode, we need both hidden and display fields
            $schema[] = Hidden::make('elementable_type');
            $elementTypeField = TextInput::make('elementable_type_display')
                ->label('Element Type')
                ->disabled(true)
                ->dehydrated(false)
                ->formatStateUsing(function ($state, callable $get) {
                    $elementType = $get('elementable_type');
                    return FormElement::getAvailableElementTypes()[$elementType] ?? $elementType;
                });
        } else {
            // For create and view modes
            $elementTypeField = $isCreate
                ? Select::make('elementable_type')
                    ->label('Element Type')
                    ->options(FormElement::getAvailableElementTypes())
                    ->required()
                    ->live()
                    ->disabled($disabled || ($disabledCallback && $disabledCallback()))
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Clear existing elementable data when type changes
                        $set('elementable_data', []);

                        // Populate with defaults from the new element type
                        if ($state && class_exists($state)) {
                            $defaults = self::getElementTypeDefaults($state);

                            if (!empty($defaults)) {
                                $set('elementable_data', $defaults);
                            }
                        }
                    })
                : TextInput::make('elementable_type')
                    ->label('Element Type')
                    ->disabled(true);
        }

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $elementTypeField = $elementTypeField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Various inputs, containers for grouping and repeating, text info for paragraphs, or custom HTML');
            });
        }

        $schema[] = $elementTypeField;

        // Description field
        $schema[] = Textarea::make('description')
            ->rows(3)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Help text field
        $helpTextField = TextInput::make('help_text')
            ->maxLength(500)
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $helpTextField = $helpTextField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'This text is read aloud by screen readers to describe the element');
            });
        }

        $schema[] = $helpTextField;

        // Visibility toggles
        $schema[] = Grid::make(2)
            ->schema([
                Toggle::make('visible_web')
                    ->label('Visible on Web')
                    ->default(true)
                    ->disabled($disabled || ($disabledCallback && $disabledCallback())),
                Toggle::make('visible_pdf')
                    ->label('Visible on PDF')
                    ->default(true)
                    ->disabled($disabled || ($disabledCallback && $disabledCallback())),
            ]);
        $customVisibilityField = TextArea::make('custom_visibility')
            ->label('Custom Visibility Script')
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $customVisibilityField = $customVisibilityField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Custom visibility script to control when this element is shown. Use the format: "if (condition) { return true; } else { return false; }". This will be evaluated in the browser.');
            });
        }

        $schema[] = Grid::make(1)
            ->schema([
                $customVisibilityField,
            ]);

        // Required and Template toggles
        $templateToggle = Toggle::make('is_template')
            ->label('Is Template')
            ->default(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $templateToggle = $templateToggle->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'If this element should be a template for later reuse');
            });
        }

        $requirementToggle = Toggle::make('is_required')
            ->label('Is Required')
            ->default(false);

        // For view mode or when disabled callback is true, disable the is_required toggle too
        if ($disabled || ($disabledCallback && $disabledCallback())) {
            $requirementToggle = $requirementToggle->disabled(true);
        }

        $schema[] = Grid::make(2)
            ->schema([
                $requirementToggle,
                $templateToggle,
            ]);

        // Read Only and Save on Submit toggles
        $readOnlyToggle = Toggle::make('is_read_only')
            ->label('Is Read Only')
            ->default(false)
            ->live()
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        $saveOnSubmitToggle = Toggle::make('save_on_submit')
            ->label('Save on Submit')
            ->default(true)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $saveOnSubmitToggle = $saveOnSubmitToggle->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'If this element\'s data should be saved when the form is submitted');
            });
        }

        $schema[] = Grid::make(2)
            ->schema([
                $readOnlyToggle,
                $saveOnSubmitToggle,
            ]);

        $schema[] = Grid::make(1)
            ->schema([
                TextArea::make('custom_read_only')
                    ->label('Custom Read Only Script')
                    ->visible(fn($get) => $get('is_read_only'))
                    ->reactive()
                    ->disabled($disabled || ($disabledCallback && $disabledCallback()))
                    ->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Custom read only script to control when this element is read only. Use the format: "if (condition) { return true; } else { return false; }". This will be evaluated in the browser.'),
            ]);


        // Tags field
        $tagsField = Select::make('tags')
            ->label('Tags')
            ->multiple()
            ->searchable()
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        if ($shouldShowTooltipsCallback) {
            $tagsField = $tagsField->when($shouldShowTooltipsCallback, function ($component) {
                return $component->hintIcon('heroicon-m-question-mark-circle', tooltip: 'Categorize related fields (use camelCase)');
            });
        }

        // Different handling for edit vs create modes
        if ($isEdit || $mode === 'view') {
            $tagsField = $tagsField->relationship('tags', 'name');
            if ($mode === 'view') {
                $tagsField = $tagsField->preload();
            }
        }

        if ($isCreate || $isEdit) {
            $tagsField = $tagsField
                ->options(fn() => FormElementTag::pluck('name', 'id')->toArray())
                ->preload();

            // Only add createOptionAction for create mode to avoid modal stacking issues
            if ($isCreate) {
                $tagsField = $tagsField
                    ->createOptionAction(
                        fn(Forms\Components\Actions\Action $action) => $action
                            ->modalHeading('Create Tag')
                            ->modalWidth('md')
                    )
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->unique(FormElementTag::class, 'name'),
                        Textarea::make('description')
                            ->rows(3),
                    ])
                    ->createOptionUsing(function (array $data) {
                        $tag = FormElementTag::create($data);
                        return $tag->id;
                    });
            }
        }

        $schema[] = $tagsField;

        return $schema;
    }

    /**
     * Get the General tab schema for create forms (BuildFormVersion)
     *
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @param bool $includeTemplateSelector Whether to include template selector
     * @param callable|null $disabledCallback Callback to determine if fields should be disabled
     * @return array The schema array
     */
    public static function getCreateSchema(
        ?callable $shouldShowTooltipsCallback = null,
        bool $includeTemplateSelector = true,
        ?callable $disabledCallback = null
    ): array {
        return self::getGeneralTabSchema(
            mode: 'create',
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            includeTemplateSelector: $includeTemplateSelector,
            disabledCallback: $disabledCallback
        );
    }

    /**
     * Get the General tab schema for edit forms (FormElementTreeBuilder edit)
     *
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @return array The schema array
     */
    public static function getEditSchema(
        ?callable $shouldShowTooltipsCallback = null
    ): array {
        return self::getGeneralTabSchema(
            mode: 'edit',
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            includeTemplateSelector: false
        );
    }

    /**
     * Get the General tab schema for view forms (FormElementTreeBuilder view)
     *
     * @param callable|null $shouldShowTooltipsCallback Callback to determine if tooltips should be shown
     * @return array The schema array
     */
    public static function getViewSchema(
        ?callable $shouldShowTooltipsCallback = null
    ): array {
        return self::getGeneralTabSchema(
            mode: 'view',
            shouldShowTooltipsCallback: $shouldShowTooltipsCallback,
            includeTemplateSelector: false
        );
    }

    /**
     * Get default values for a given element type.
     *
     * @param string $elementType The class name of the element type.
     * @return array An associative array of field names and their default values.
     */
    private static function getElementTypeDefaults(string $elementType): array
    {
        $defaults = [];

        // Use the model's default attributes instead of calling getDefaultState()
        if (class_exists($elementType)) {
            $model = new $elementType();

            // Check if the model has a getDefaultData method
            if (method_exists($elementType, 'getDefaultData')) {
                $defaults = $elementType::getDefaultData();
            } else {
                // Fall back to model attributes for other element types
                $defaults = $model->getAttributes();
            }
        }

        return $defaults;
    }
}
