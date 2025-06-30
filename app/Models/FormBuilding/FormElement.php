<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class FormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'name',
        'order',
        'description',
        'parent_id',
        'form_version_id',
        'elementable_type',
        'elementable_id',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($formElement) {
            if (empty($formElement->uuid)) {
                $formElement->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the form version that owns the form element.
     */
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
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
     * Check if this element is of a specific type
     */
    public function isType(string $type): bool
    {
        return $this->elementable_type === $type ||
            class_basename($this->elementable_type) === $type;
    }

    /**
     * Get all available element types
     */
    public static function getAvailableElementTypes(): array
    {
        return [
            TextInfoFormElement::class => 'Text Info',
            ButtonInputFormElement::class => 'Button Input',
            TextInputFormElement::class => 'Text Input',
            TextareaInputFormElement::class => 'Textarea Input',
            NumberInputFormElement::class => 'Number Input',
            DateSelectInputFormElement::class => 'Date Select Input',
            ContainerFormElement::class => 'Container',
            HTMLFormElement::class => 'HTML',
            CheckboxInputFormElement::class => 'Checkbox Input',
            SelectInputFormElement::class => 'Select Input',
            RadioInputFormElement::class => 'Radio Input',
        ];
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
}
