<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Concern\ModelTree;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FormElement extends Model
{
    use HasFactory, ModelTree, LogsActivity, SoftDeletes;

    protected $fillable = [
        'uuid',
        'reference_id',
        'name',
        'order',
        'description',
        'parent_id',
        'form_version_id',
        'elementable_type',
        'elementable_id',
        'help_text',
        'calculated_value',
        'is_read_only',
        'custom_read_only',
        'is_required',
        'save_on_submit',
        'visible_web',
        'visible_pdf',
        'custom_visibility',
        'is_template',
        'source_element_id',
    ];

    protected $casts = [
        'order' => 'integer',
        'parent_id' => 'integer',
        'is_read_only' => 'boolean',
        'is_required' => 'boolean',
        'save_on_submit' => 'boolean',
        'visible_web' => 'boolean',
        'visible_pdf' => 'boolean',
        'is_template' => 'boolean',
    ];

    protected static $logAttributes = [
        'name',
        'order',
        'description',
        'parent_id',
        'form_version_id',
        'elementable_type',
        'help_text',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($formElement) {
            if (empty($formElement->uuid)) {
                $formElement->uuid = (string) Str::uuid();
            }
        });

        // Validate parent-child relationships
        static::saving(function ($formElement) {
            // If this element is being assigned a parent, validate that the parent can have children
            if ($formElement->parent_id && $formElement->parent_id !== -1) {
                $parent = self::find($formElement->parent_id);
                if ($parent && !$parent->canHaveChildren()) {
                    throw new \InvalidArgumentException("Only container elements can have children. Parent element '{$parent->name}' (type: {$parent->element_type}) cannot contain child elements.");
                }
            }
        });

        static::updating(function ($formElement) {
            // If this element is changing to a non-container type and has children, prevent the change
            if ($formElement->isDirty('elementable_type') && !$formElement->canHaveChildren()) {
                $childrenCount = self::where('parent_id', $formElement->id)->count();
                if ($childrenCount > 0) {
                    throw new \InvalidArgumentException("Cannot change element type to '{$formElement->element_type}' because it has {$childrenCount} child element(s). Only container elements can have children.");
                }
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $elementName = $this->name ?: 'Unnamed Element';
                $formVersion = $this->formVersion ? $this->formVersion->version_number : 'Unknown Form Version';
                $formTitle = $this->form ? $this->form->form_title : 'Unknown Form';

                if ($eventName === 'created') {
                    return "{$elementName} created on Form: {$formTitle}, Version: {$formVersion}";
                }

                $changes = array_keys($this->getDirty());
                $changes = array_filter($changes, function ($change) {
                    return !in_array($change, ['updated_at']);
                });

                if (!empty($changes)) {
                    $changes = array_map(function ($change) {
                        $change = str_replace('_', ' ', $change);
                        $change = str_replace('form developer', 'developer', $change);
                        return $change;
                    }, $changes);

                    $changesStr = implode(', ', array_unique($changes));
                    return "{$elementName} had changes to: {$changesStr} on Form: {$formTitle}, Version: {$formVersion}";
                }

                return "{$elementName} was {$eventName} on Form: {$formTitle}, Version: {$formVersion} ";
            });
    }

    public function getLogNameToUse(): string
    {
        return 'form_elements';
    }

    /**
     * Get the form version that owns the form element.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    /**
     * Get the form that owns the form element
     */
    public function getFormAttribute()
    {
        return $this->formVersion ? $this->formVersion->form : null;
    }

    /**
     * Get the elementable model (TextInfoFormElement, ButtonInputFormElement, etc.)
     */
    public function elementable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the parent form element.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormElement::class, 'parent_id');
    }

    /**
     * Get the children form elements.
     */
    public function children(): HasMany
    {
        return $this->hasMany(FormElement::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get the tags associated with this form element.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(FormElementTag::class, 'form_element_form_element_tag');
    }

    /**
     * Get the data bindings for this form element.
     */
    public function dataBindings(): HasMany
    {
        return $this->hasMany(FormElementDataBinding::class)->orderBy('order');
    }

    /**
     * Get the source element (template) this element was created from.
     */
    public function sourceElement(): BelongsTo
    {
        return $this->belongsTo(FormElement::class, 'source_element_id');
    }

    /**
     * Get all elements that were created from this template.
     */
    public function derivedElements(): HasMany
    {
        return $this->hasMany(FormElement::class, 'source_element_id');
    }

    /**
     * Scope to get root elements (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get elements for a specific form version
     */
    public function scopeForFormVersion($query, $formVersionId)
    {
        return $query->where('form_version_id', $formVersionId);
    }

    /**
     * Scope to order by the order field
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Scope to get visible elements
     */
    public function scopeVisible($query)
    {
        return $query->where('visible_web', true);
    }

    /**
     * Scope to get elements visible on web
     */
    public function scopeVisibleWeb($query)
    {
        return $query->where('visible_web', true);
    }

    /**
     * Scope to get elements visible on PDF
     */
    public function scopeVisiblePdf($query)
    {
        return $query->where('visible_pdf', true);
    }

    /**
     * Scope to get read-only elements
     */
    public function scopeReadOnly($query)
    {
        return $query->where('is_read_only', true);
    }

    public function scopeIsRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to get editable elements
     */
    public function scopeEditable($query)
    {
        return $query->where('is_read_only', false);
    }

    /**
     * Scope to get elements that save on submit
     */
    public function scopeSaveOnSubmit($query)
    {
        return $query->where('save_on_submit', true);
    }

    /**
     * Scope to get template elements
     */
    public function scopeTemplates($query)
    {
        return $query->where('is_template', true);
    }

    /**
     * Scope to get non-template elements
     */
    public function scopeNonTemplates($query)
    {
        return $query->where('is_template', false);
    }

    /**
     * Scope to filter by element type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('elementable_type', $type);
    }

    /**
     * Get the element type (class name without namespace)
     */
    public function getElementTypeAttribute(): ?string
    {
        if (!$this->elementable_type) {
            return null;
        }

        return class_basename($this->elementable_type);
    }

    /**
     * Get the full reference ID (reference_id + uuid with hyphen)
     * Falls back to just uuid if reference_id is empty
     */
    public function getFullReferenceId(): string
    {
        return $this->reference_id ? $this->reference_id . '-' . $this->uuid : $this->uuid;
    }

    /**
     * Static helper to construct full reference ID from separate values
     * Falls back to just uuid if reference_id is empty
     */
    public static function buildFullReferenceId(?string $referenceId, string $uuid): string
    {
        return $referenceId ? $referenceId . '-' . $uuid : $uuid;
    }

    /**
     * Check if this element is of a specific type
     */
    public function isType(string $type): bool
    {
        return $this->elementable_type === $type ||
            class_basename($this->elementable_type) === $type;
    }

    /**
     * Check if this element is a container type
     */
    public function isContainer(): bool
    {
        return $this->elementable_type === ContainerFormElement::class;
    }

    /**
     * Check if this element can have children
     * Only container elements can have children
     */
    public function canHaveChildren(): bool
    {
        return $this->isContainer();
    }

    /**
     * Check if this element is a template
     */
    public function isTemplate(): bool
    {
        return $this->is_template;
    }

    /**
     * Check if this element was created from a template
     */
    public function wasCreatedFromTemplate(): bool
    {
        return $this->source_element_id !== null;
    }

    /**
     * Get the template this element was created from
     */
    public function getSourceTemplate(): ?self
    {
        return $this->sourceElement;
    }

    /**
     * Get all available element types
     */
    public static function getAvailableElementTypes(): array
    {
        return [
            ContainerFormElement::class => 'Container',
            TextInputFormElement::class => 'Text Input',
            TextareaInputFormElement::class => 'Textarea',
            TextInfoFormElement::class => 'Text Display',
            DateSelectInputFormElement::class => 'Date Select',
            CheckboxInputFormElement::class => 'Checkbox',
            CheckboxGroupFormElement::class => 'Checkbox Group',
            SelectInputFormElement::class => 'Dropdown',
            RadioInputFormElement::class => 'Radio',
            NumberInputFormElement::class => 'Number Input',
            CurrencyInputFormElement::class => 'Currency',
            ButtonInputFormElement::class => 'Button',
            HTMLFormElement::class => 'HTML',
        ];
    }

    /**
     * Get formatted element type name
     */
    public static function getElementTypeName(string $elementType): ?string
    {
        $availableTypes = static::getAvailableElementTypes();

        return $availableTypes[$elementType] ?? null;
    }

    /**
     * Create a text info form element
     */
    public static function createTextInfo(array $elementData, array $textInfoData): self
    {
        $textInfo = TextInfoFormElement::create($textInfoData);

        $elementData['elementable_type'] = TextInfoFormElement::class;
        $elementData['elementable_id'] = $textInfo->id;

        return self::create($elementData);
    }

    /**
     * Create a button input form element
     */
    public static function createButton(array $elementData, array $buttonData): self
    {
        $button = ButtonInputFormElement::create($buttonData);

        $elementData['elementable_type'] = ButtonInputFormElement::class;
        $elementData['elementable_id'] = $button->id;

        return self::create($elementData);
    }

    /**
     * Create a text input form element
     */
    public static function createTextInput(array $elementData, array $textInputData): self
    {
        $textInput = TextInputFormElement::create($textInputData);

        $elementData['elementable_type'] = TextInputFormElement::class;
        $elementData['elementable_id'] = $textInput->id;

        return self::create($elementData);
    }

    /**
     * Create a textarea input form element
     */
    public static function createTextarea(array $elementData, array $textareaData): self
    {
        $textarea = TextareaInputFormElement::create($textareaData);

        $elementData['elementable_type'] = TextareaInputFormElement::class;
        $elementData['elementable_id'] = $textarea->id;

        return self::create($elementData);
    }

    /**
     * Create a number input form element
     */
    public static function createNumber(array $elementData, array $numberData): self
    {
        $number = NumberInputFormElement::create($numberData);

        $elementData['elementable_type'] = NumberInputFormElement::class;
        $elementData['elementable_id'] = $number->id;

        return self::create($elementData);
    }

/**
     * Create a currency input form element
     */
    public static function createCurrency(array $elementData, array $currencyData): self
    {
        $currency = CurrencyInputFormElement::create($currencyData);
        $elementData['elementable_type'] = CurrencyInputFormElement::class;
        $elementData['elementable_id'] = $currency->id;

        return self::create($elementData);
    }    

    /**
     * Create a date select input form element
     */
    public static function createDateSelect(array $elementData, array $dateSelectData): self
    {
        $dateSelect = DateSelectInputFormElement::create($dateSelectData);

        $elementData['elementable_type'] = DateSelectInputFormElement::class;
        $elementData['elementable_id'] = $dateSelect->id;

        return self::create($elementData);
    }

    /**
     * Create a container form element
     */
    public static function createContainer(array $elementData, array $containerData): self
    {
        $container = ContainerFormElement::create($containerData);

        $elementData['elementable_type'] = ContainerFormElement::class;
        $elementData['elementable_id'] = $container->id;

        return self::create($elementData);
    }

    /**
     * Create an HTML form element
     */
    public static function createHTML(array $elementData, array $htmlData): self
    {
        $html = HTMLFormElement::create($htmlData);

        $elementData['elementable_type'] = HTMLFormElement::class;
        $elementData['elementable_id'] = $html->id;

        return self::create($elementData);
    }

    /**
     * Create a checkbox input form element
     */
    public static function createCheckbox(array $elementData, array $checkboxData): self
    {
        $checkbox = CheckboxInputFormElement::create($checkboxData);

        $elementData['elementable_type'] = CheckboxInputFormElement::class;
        $elementData['elementable_id'] = $checkbox->id;

        return self::create($elementData);
    }

    /**
     * Create a checkbox group form element
     */
    public static function createCheckboxGroup(array $elementData, array $checkboxGroupData = [], array $options = []): self
    {
        $checkboxGroup = CheckboxGroupFormElement::create($checkboxGroupData);

        $elementData['elementable_type'] = CheckboxGroupFormElement::class;
        $elementData['elementable_id'] = $checkboxGroup->id;

        $element = self::create($elementData);

        // Add options if provided
        foreach ($options as $index => $optionData) {
            if (!isset($optionData['order'])) {
                $optionData['order'] = $index + 1;
            }
            SelectOptionFormElement::createForCheckboxGroup($checkboxGroup, $optionData);
        }

        return $element;
    }

    /**
     * Create a select input form element
     */
    public static function createSelect(array $elementData, array $selectData, array $options = []): self
    {
        $select = SelectInputFormElement::create($selectData);

        $elementData['elementable_type'] = SelectInputFormElement::class;
        $elementData['elementable_id'] = $select->id;

        $element = self::create($elementData);

        // Add options if provided
        foreach ($options as $index => $optionData) {
            if (!isset($optionData['order'])) {
                $optionData['order'] = $index + 1;
            }
            SelectOptionFormElement::createForSelect($select, $optionData);
        }

        return $element;
    }

    /**
     * Create a radio input form element
     */
    public static function createRadio(array $elementData, array $radioData, array $options = []): self
    {
        $radio = RadioInputFormElement::create($radioData);

        $elementData['elementable_type'] = RadioInputFormElement::class;
        $elementData['elementable_id'] = $radio->id;

        $element = self::create($elementData);

        // Add options if provided
        foreach ($options as $index => $optionData) {
            if (!isset($optionData['order'])) {
                $optionData['order'] = $index + 1;
            }
            SelectOptionFormElement::createForRadio($radio, $optionData);
        }

        return $element;
    }

    /**
     * Add an option to a select or radio element
     */
    public function addOption(array $optionData): ?SelectOptionFormElement
    {
        if (!$this->elementable) {
            return null;
        }

        if ($this->elementable instanceof SelectInputFormElement) {
            return SelectOptionFormElement::createForSelect($this->elementable, $optionData);
        } elseif ($this->elementable instanceof RadioInputFormElement) {
            return SelectOptionFormElement::createForRadio($this->elementable, $optionData);
        }

        return null;
    }

    /**
     * Get options for select or radio elements
     */
    public function getOptions()
    {
        if (!$this->elementable) {
            return collect();
        }

        if (
            $this->elementable instanceof SelectInputFormElement ||
            $this->elementable instanceof RadioInputFormElement
        ) {
            return $this->elementable->options;
        }

        return collect();
    }

    /**
     * Check if element is visible for a specific platform
     */
    public function isVisibleFor(string $platform): bool
    {
        return match ($platform) {
            'web' => $this->visible_web,
            'pdf' => $this->visible_pdf,
            default => false,
        };
    }

    /**
     * Check if element should be included in form submission
     */
    public function shouldSaveOnSubmit(): bool
    {
        return $this->save_on_submit && ($this->isVisibleFor('web') || $this->isVisibleFor('pdf')) && !$this->is_read_only;
    }

    /**
     * Set visibility for specific platforms
     */
    public function setVisibilityFor(array $platforms): self
    {
        $this->visible_web = in_array('web', $platforms);
        $this->visible_pdf = in_array('pdf', $platforms);

        return $this;
    }

    /**
     * Override the title column name for Filament Tree plugin
     */
    public function determineTitleColumnName(): string
    {
        return 'name';
    }

    /**
     * Override the default parent key for Filament Tree plugin
     */
    public static function defaultParentKey()
    {
        return -1;
    }

    /**
     * Clone this element and all its children recursively
     *
     * @param int $formVersionId The target form version ID
     * @param int|null $parentId The parent ID for the cloned element
     * @return self The cloned element
     */
    public function cloneWithChildren(int $formVersionId, ?int $parentId = null): self
    {
        // Load the element with all necessary relationships
        $this->load(['elementable', 'tags', 'dataBindings', 'children']);

        // Prepare data for the new element
        $elementData = $this->toArray();

        // Remove fields that should not be copied (UUID will be auto-generated, but keep reference_id)
        // Also remove order so it gets placed at the bottom
        unset($elementData['id'], $elementData['created_at'], $elementData['updated_at'], $elementData['uuid'], $elementData['order']);

        // Set the new form version and parent
        $elementData['form_version_id'] = $formVersionId;
        $elementData['parent_id'] = $parentId;
        $elementData['is_template'] = false; // Cloned elements should not be templates

        // Keep the reference_id from the template
        $elementData['reference_id'] = $this->reference_id;

        // Set order to place at the bottom
        if ($parentId) {
            // Get the highest order for children of this parent
            $maxOrder = self::where('parent_id', $parentId)->max('order') ?? 0;
            $elementData['order'] = $maxOrder + 1;
        } else {
            // Get the highest order for root elements in this form version
            $maxOrder = self::where('form_version_id', $formVersionId)
                ->where(function ($query) {
                    $query->whereNull('parent_id')->orWhere('parent_id', -1);
                })
                ->max('order') ?? 0;
            $elementData['order'] = $maxOrder + 1;
        }

        // Clone the elementable model first
        $elementableModel = null;
        if ($this->elementable) {
            $elementableData = $this->elementable->toArray();
            // Remove timestamps and primary key
            unset($elementableData['id'], $elementableData['created_at'], $elementableData['updated_at']);

            $elementableModel = $this->elementable_type::create($elementableData);
            $elementData['elementable_id'] = $elementableModel->id;
        }

        // Create the new form element
        $newElement = self::create($elementData);

        // Clone options for select/radio elements
        if ($elementableModel && method_exists($elementableModel, 'options')) {
            $options = $this->elementable->options()->ordered()->get();
            foreach ($options as $option) {
                $optionData = $option->toArray();
                unset($optionData['id'], $optionData['created_at'], $optionData['updated_at']);

                if ($elementableModel instanceof \App\Models\FormBuilding\SelectInputFormElement) {
                    \App\Models\FormBuilding\SelectOptionFormElement::createForSelect($elementableModel, $optionData);
                } elseif ($elementableModel instanceof \App\Models\FormBuilding\RadioInputFormElement) {
                    \App\Models\FormBuilding\SelectOptionFormElement::createForRadio($elementableModel, $optionData);
                }
            }
        }

        // Clone tags
        if ($this->tags->isNotEmpty()) {
            $newElement->tags()->attach($this->tags->pluck('id'));
        }

        // Clone data bindings
        foreach ($this->dataBindings as $dataBinding) {
            \App\Models\FormBuilding\FormElementDataBinding::create([
                'form_element_id' => $newElement->id,
                'form_data_source_id' => $dataBinding->form_data_source_id,
                'path' => $dataBinding->path,
                'condition' => $dataBinding->condition,
                'order' => $dataBinding->order,
            ]);
        }

        // Recursively clone children
        foreach ($this->children()->ordered()->get() as $child) {
            $child->cloneWithChildren($formVersionId, $newElement->id);
        }

        return $newElement;
    }
}
