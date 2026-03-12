<?php

namespace App\Helpers;

use App\Models\FormBuilding\FormElement;
use App\Models\FormBuilding\FormElementTag;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms;
use App\Livewire\FormElementTreeBuilder as Builder;
use Illuminate\Support\Str;

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
            $schema[] = self::makeTemplateField($shouldShowTooltipsCallback);
        }

        // Name field
        $schema[] = self::makeNameField($isCreate, $disabled, $disabledCallback, $shouldShowTooltipsCallback);

        // Hidden ID field
        $schema[] = Hidden::make('id')->dehydrated(false);

        // Reference ID field - editable on create and edit
        $schema[] = self::makeReferenceIdField($mode, $disabled, $disabledCallback, $shouldShowTooltipsCallback);

        // Element Type field (different handling for edit vs create/view)
        $schema[] = self::makeElementTypeField($mode, $disabled, $disabledCallback, $shouldShowTooltipsCallback);

        // Description field
        $schema[] = Textarea::make('description')
            ->rows(3)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Help text field
        $schema[] = self::makeHelpTextField($disabled, $disabledCallback, $shouldShowTooltipsCallback);

        // Visibility grid
        $schema[] = self::makeVisibilityGrid($disabled, $disabledCallback);

        // Required grid
        $schema[] = self::makeRequiredGrid($disabled, $disabledCallback);

        // Template toggles
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

        // Read Only and Save on Submit toggles
        $readOnlyBool = Toggle::make('is_read_only_toggle')
            ->label('Is Read Only')
            ->default(false)
            ->live()
            ->disabled($disabled || ($disabledCallback && $disabledCallback()))
            ->afterStateHydrated(function (Toggle $component, callable $set, callable $get) {
                $isReadOnly = $get('is_read_only');
                // Set toggle to true if is_read_only has any non-null value ('always' or 'portal')
                if ($isReadOnly !== null && $isReadOnly !== '') {
                    $set('is_read_only_toggle', true);
                }
            });

        $readOnlyToggleButtons = ToggleButtons::make('is_read_only')
            ->label('Read Only When')
            ->options([
                'always' => 'Always',
                'portal' => 'On Portal Forms'
            ])
            ->default('always')
            ->inline()
            ->disabled(fn($get) => !$get('is_read_only_toggle'))
            ->afterStateHydrated(function (callable $set, callable $get) {
                $value = $get('is_read_only');
                if ($value === null || $value === '') {
                    $set('is_read_only', 'always');
                }
            });

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

        $customReadOnlyField = Grid::make(1)
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

        // Organize visibility, validation, behaviour, and metadata fields
        $schema[] = Grid::make(2)
            ->schema([
                $readOnlyBool,
                $readOnlyToggleButtons,
                $customReadOnlyField,
                $templateToggle,
                $saveOnSubmitToggle,
                $tagsField->columnSpanFull(),
            ]);

        return $schema;
    }

    private static function makeTemplateField(?callable $shouldShowTooltipsCallback): Component
    {
        $field = Select::make('template_id')
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
                $set('is_required_toggle', $template->is_required !== null && $template->is_required !== '');
                $set('is_required', $template->is_required);
                $set('is_read_only_toggle', $template->is_read_only !== null && $template->is_read_only !== '');
                $set('is_read_only', $template->is_read_only);
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
        return self::withOptionalTooltip(
            $field,
            $shouldShowTooltipsCallback,
            'Select a template to start with pre-configured settings. For containers, this will also clone all child elements.'
        );
    }

    private static function makeNameField(
        bool $isCreate,
        bool $disabled,
        ?callable $disabledCallback,
        ?callable $shouldShowTooltipsCallback
    ): Component {
        $field = TextInput::make('name')
            ->required()
            ->maxLength(255)
            ->label('Element Name')
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add auto-generation logic for create mode
        if ($isCreate) {
            $field = $field
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    // Auto-generate reference_id if it's empty and we have a name
                    if (!empty($state) && empty($get('reference_id'))) {
                        // Replace slashes and backslashes with dashes before slugifying
                        $preparedState = preg_replace('/[\/\\\\]/', '-', $state);
                        $slug = Str::slug($preparedState, '-');
                        $set('reference_id', $slug);
                    }
                });
        }

        // Add tooltip if callback is provided
        return self::withOptionalTooltip(
            $field,
            $shouldShowTooltipsCallback,
            'Human friendly identifier to help you find and reference this element'
        );
    }

    private static function makeReferenceIdField(
        string $mode,
        bool $disabled,
        ?callable $disabledCallback,
        ?callable $shouldShowTooltipsCallback
    ): Component {
        $field = TextInput::make('reference_id')
            ->label('Reference ID')
            ->rules(['alpha_dash'])
            ->live()
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()))
            ->suffixIcon(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if ($lw instanceof Builder && $lw->isFieldInvalid((int) $get('id'), 'reference_id')) {
                    return 'heroicon-o-exclamation-triangle';
                }
                return null;
            })
            ->hint(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if ($lw instanceof Builder && $lw->isFieldInvalid((int) $get('id'), 'reference_id')) {
                    return 'Invalid: ' . ($lw->invalidReason((int) $get('id'), 'reference_id') ?? '');
                }
                return null;
            })
            // make the "?" mark color back to normal when clean
            ->hintColor(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                return ($lw instanceof Builder && $lw->isFieldInvalid((int) $get('id'), 'reference_id')) ? 'danger' : 'gray';
            })
            ->extraAttributes(function (Forms\Get $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if ($lw instanceof Builder && $lw->isFieldInvalid((int) $get('id'), 'reference_id')) {
                    return ['class' => 'ring-1 ring-danger-500 bg-danger-50/50'];
                }
                return [];
            })
            ->afterStateUpdated(function ($state, $set, $get, TextInput $component) {
                $lw = method_exists($component, 'getLivewire') ? $component->getLivewire() : null;
                if (!($lw instanceof Builder))
                    return;

                $id = (int) $get('id');
                $value = is_string($state) ? trim($state) : '';

                if ($value !== '' && !preg_match('/^\d/', $value)) {
                    $lw->clearInvalidMarker($id, 'reference_id');
                } else {
                    $lw->markInvalid($id, 'reference_id', $value === '' ? 'empty' : 'starts with a number', $value);
                }

                // update row highlights in the child builder
                $lw->dispatch('ff-markers-updated', markers: $lw->invalidByElement ?? [])
                    ->to('form-element-tree-builder');
            });

        // Add tooltip if callback is provided
        $field = self::withOptionalTooltip(
            $field,
            $shouldShowTooltipsCallback,
            'Human-readable identifier to aid creating ICM data bindings'
        );

        // Add suffix and actions for edit mode
        if ($mode === 'edit') {
            // $schema[] = Hidden::make('reference_id_locked');
            return $field
                ->prefixAction(
                    Action::make('toggleLock')
                        ->icon(fn($get) => $get('reference_id_locked') ? 'heroicon-s-lock-open' : 'heroicon-s-lock-closed')
                        ->tooltip(fn($get) => $get('reference_id_locked') ? '' : 'Click to unlock and edit the Reference ID. Changing this value may break ICM data bindings, scripts, and/or styles.')
                        ->action(fn($set) => $set('reference_id_locked', true))
                )
                ->disabled(fn($get) => !$get('reference_id_locked'))
                ->suffix(function ($get) {
                    return $get('uuid') ? $get('uuid') : '';
                })
                ->autocomplete(false)
                ->suffixActions([
                    self::makeCopyScriptAction(),
                    self::makeCopyCssAction(),
                ])
                ->extraAttributes(self::getCopyToClipboardText());

        } elseif ($mode === 'create') {
            // Add regenerate action for create mode
            return $field->suffixAction(
                Action::make('regenerate_reference_id')
                    ->icon('heroicon-o-arrow-path')
                    ->tooltip('Regenerate from Element Name')
                    ->action(function (callable $set, callable $get) {
                        $name = $get('name');
                        if (!empty($name)) {
                            $slug = Str::slug($name, '-');
                            $set('reference_id', $slug);
                        }
                    })
            );

        } else { // $mode === 'view'
            // Add copy functionality for view mode
            return $field
                ->suffix(function ($get) {
                    return $get('uuid') ? $get('uuid') : '';
                })
                ->suffixActions([
                    self::makeCopyScriptAction(),
                    self::makeCopyCssAction(),
                ])
                ->extraAttributes(self::getCopyToClipboardText());
        }
    }

    private static function makeElementTypeField(
        string $mode,
        bool $disabled,
        ?callable $disabledCallback,
        ?callable $shouldShowTooltipsCallback
    ): Component {
        $tooltip = 'Various inputs, containers for grouping and repeating, text info for paragraphs, or custom HTML';

        if ($mode === 'edit') {
            $field = TextInput::make('elementable_type_display')
                ->label('Element Type')
                ->disabled(true)
                ->dehydrated(false)
                ->formatStateUsing(function ($state, callable $get) {
                    $elementType = $get('elementable_type');
                    return FormElement::getAvailableElementTypes()[$elementType] ?? $elementType;
                });

            // For edit mode, we need both hidden and display fields
            return Grid::make(1)
                ->schema([
                    Hidden::make('elementable_type'),
                    self::withOptionalTooltip(
                        $field,
                        $shouldShowTooltipsCallback,
                        $tooltip,
                    ),
                ]);

        } else if ($mode === 'create') {
            $field = ToggleButtons::make('elementable_type')
                ->label(function (?string $state): string {
                    $elementType = $state ? FormElement::getElementTypeName($state) : '';
                    return $elementType ? "Element Type: {$elementType}" : 'Element Type';
                })
                ->options(FormElement::getAvailableElementTypes())
                ->inline()
                ->icons([
                    'App\Models\FormBuilding\TextInputFormElement' => 'heroicon-o-pencil-square',
                    'App\Models\FormBuilding\TextareaInputFormElement' => 'heroicon-o-document-text',
                    'App\Models\FormBuilding\SelectInputFormElement' => 'heroicon-o-queue-list',
                    'App\Models\FormBuilding\RadioInputFormElement' => 'heroicon-o-radio',
                    'App\Models\FormBuilding\CheckboxInputFormElement' => 'heroicon-o-check-circle',
                    'App\Models\FormBuilding\CheckboxGroupFormElement' => 'heroicon-o-list-bullet',
                    'App\Models\FormBuilding\DateSelectInputFormElement' => 'heroicon-o-calendar',
                    'App\Models\FormBuilding\NumberInputFormElement' => 'heroicon-o-calculator',
                    'App\Models\FormBuilding\CurrencyInputFormElement' => 'heroicon-o-currency-dollar',
                    'App\Models\FormBuilding\ContainerFormElement' => 'heroicon-o-rectangle-group',
                    'App\Models\FormBuilding\TextInfoFormElement' => 'heroicon-o-information-circle',
                    'App\Models\FormBuilding\ButtonInputFormElement' => 'heroicon-o-cursor-arrow-ripple',
                    'App\Models\FormBuilding\HTMLFormElement' => 'heroicon-o-code-bracket',
                ])
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
                });

            return self::withOptionalTooltip(
                $field,
                $shouldShowTooltipsCallback,
                $tooltip,
            );

        } else { // $mode === 'view'
            $field = TextInput::make('elementable_type')
                ->label('Element Type')
                ->disabled(true);

            // Add tooltip if callback is provided
            return self::withOptionalTooltip(
                $field,
                $shouldShowTooltipsCallback,
                $tooltip,
            );
        }
    }

    private static function makeHelpTextField(
        bool $disabled,
        ?callable $disabledCallback,
        ?callable $shouldShowTooltipsCallback
    ): Component {
        $field = TextInput::make('help_text')
            ->maxLength(500)
            ->autocomplete(false)
            ->disabled($disabled || ($disabledCallback && $disabledCallback()));

        // Add tooltip if callback is provided
        return self::withOptionalTooltip(
            $field,
            $shouldShowTooltipsCallback,
            'This text is read aloud by screen readers to describe the element',
        );
    }

    private static function makeVisibilityGrid(bool $disabled, ?callable $disabledCallback): Component
    {
        return Grid::make(2)
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
    }

    private static function makeRequiredGrid(bool $disabled, ?callable $disabledCallback): Component
    {
        $toggle = Toggle::make('is_required_toggle')
            ->label('Is Required')
            ->default(false)
            ->live()
            ->disabled($disabled || ($disabledCallback && $disabledCallback()))
            ->afterStateHydrated(function (Toggle $component, callable $set, callable $get) {
                $isRequired = $get('is_required');
                // Set toggle to true if is_required has any non-null value ('always' or 'portal')
                if ($isRequired !== null && $isRequired !== '') {
                    $set('is_required_toggle', true);
                }
            });

        $buttons = ToggleButtons::make('is_required')
            ->label('Required When')
            ->options([
                'always' => 'Always',
                'portal' => 'On Portal Forms'
            ])
            ->default('always')
            ->inline()
            ->disabled(fn($get) => !$get('is_required_toggle'))
            ->afterStateHydrated(function (callable $set, callable $get) {
                $value = $get('is_required');
                if ($value === null || $value === '') {
                    $set('is_required', 'always');
                }
            });

        return Grid::make(2)
            ->schema([
                $toggle,
                $buttons,
            ]);
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

    private static function withOptionalTooltip(
        Component $component,
        ?callable $shouldShowTooltipsCallback,
        string $tooltip
    ): Component {
        return $shouldShowTooltipsCallback
            ? $component->when($shouldShowTooltipsCallback, fn($c) => $c->hintIcon('heroicon-m-question-mark-circle', tooltip: $tooltip))
            : $component;
    }

    private static function makeCopyScriptAction(): Action
    {
        // Copy script format:  '<ref-uuid>' /* Name (Type) */
        return Action::make('copyScript')
            ->icon('heroicon-s-clipboard')
            ->tooltip('Copy script snippet')
            ->action(function ($livewire, $state, $get) {
                $base = FormElement::buildFullReferenceId($state, $get('uuid'));

                $nameBase = (string) ($get('label') ?? $get('name') ?? '');
                $elementType = (string) ($get('elementable_type') ?? '');
                $availableTypes = FormElement::getAvailableElementTypes();
                $typeDisplay = $availableTypes[$elementType] ?? $elementType ?? 'Element';

                $label = trim($nameBase) !== '' ? ($nameBase . ' (' . $typeDisplay . ')') : $typeDisplay;
                $label = addslashes($label);
                $snippet = "'{$base}' /* {$label} */";

                $livewire->dispatch('copy-to-clipboard', text: $snippet);
            });
    }

    private static function makeCopyCssAction(): Action
    {
        // Copy CSS selector format:  [id='<ref-uuid>'] /* Name (Type) */
        return Action::make('copyCss')
            ->icon('heroicon-s-code-bracket-square')
            ->tooltip('Copy CSS selector')
            ->action(function ($livewire, $state, $get) {
                $base = FormElement::buildFullReferenceId($state, $get('uuid'));

                $nameBase = (string) ($get('label') ?? $get('name') ?? '');
                $elementType = (string) ($get('elementable_type') ?? '');
                $availableTypes = FormElement::getAvailableElementTypes();
                $typeDisplay = $availableTypes[$elementType] ?? $elementType ?? 'Element';

                $label = trim($nameBase) !== '' ? ($nameBase . ' (' . $typeDisplay . ')') : $typeDisplay;
                $label = addslashes($label);
                $snippet = "[id='{$base}'] /* {$label} */";

                $livewire->dispatch('copy-to-clipboard', text: $snippet);
            });
    }

    private static function getCopyToClipboardText(): array
    {
        return [
            'x-data' => '{
                copyToClipboard(text) {
                    if (navigator.clipboard?.writeText) {
                        navigator.clipboard.writeText(text)
                            .then(() => $tooltip("Copied to clipboard", { timeout: 1500 }))
                            .catch(() => $tooltip("Failed to copy", { timeout: 1500 }));
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
        ];
    }
}
