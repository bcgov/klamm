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
}
