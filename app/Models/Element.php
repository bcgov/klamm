<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Concern\ModelTree;

class Element extends Model
{
    use HasFactory, ModelTree;

    protected $fillable = [
        'form_version_id',
        'parent_element_id',
        'uuid',
        'type', // 'field' or 'container'
        'order',
        'custom_label',
        'hide_label',
        'custom_data_binding_path',
        'custom_data_binding',
        'custom_help_text',
        'visible_web',
        'visible_pdf',
        // Container-specific fields
        'has_repeater',
        'has_clear_button',
        'repeater_item_label',
        // Field-specific fields
        'field_template_id',
        'custom_mask',
    ];

    protected $casts = [
        'hide_label' => 'boolean',
        'visible_web' => 'boolean',
        'visible_pdf' => 'boolean',
        'has_repeater' => 'boolean',
        'has_clear_button' => 'boolean',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($element) {
            // Generate UUID if not provided
            if (empty($element->uuid)) {
                $element->uuid = (string) Str::uuid();
            }
        });
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function parentElement(): BelongsTo
    {
        return $this->belongsTo(Element::class, 'parent_element_id');
    }

    public function childElements(): HasMany
    {
        return $this->hasMany(Element::class, 'parent_element_id');
    }

    public function elementValidations(): HasMany
    {
        return $this->hasMany(ElementValidation::class);
    }

    public function elementConditionals(): HasMany
    {
        return $this->hasMany(ElementConditional::class);
    }

    // Field-specific relationships
    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class);
    }

    public function elementValue()
    {
        return $this->hasOne(ElementValue::class);
    }

    public function elementDateFormat()
    {
        return $this->hasOne(ElementDateFormat::class);
    }

    public function selectOptionInstances()
    {
        return $this->hasMany(SelectOptionInstance::class);
    }

    // Helper methods for type checking
    public function isContainer(): bool
    {
        return $this->type === 'container';
    }

    public function isField(): bool
    {
        return $this->type === 'field';
    }

    // Scope methods for querying
    public function scopeContainers($query)
    {
        return $query->where('type', 'container');
    }

    public function scopeFields($query)
    {
        return $query->where('type', 'field');
    }

    // Helper methods to access as specific type
    public function asContainer()
    {
        return $this->isContainer() ? new Container($this->attributes) : null;
    }

    public function asField()
    {
        return $this->isField() ? new Field($this->attributes) : null;
    }

    public function determineOrderColumnName(): string
    {
        return "order";
    }

    public function determineParentColumnName(): string
    {
        return "parent_element_id";
    }

    public function determineTitleColumnName(): string
    {
        return 'custom_label';
    }

    public static function defaultParentKey()
    {
        return null;
    }
}
