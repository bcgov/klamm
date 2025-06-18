<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Concern\ModelTree;

class FormElement extends Model
{
    use HasFactory, ModelTree;

    protected $fillable = [
        'token',
        'name',
        'form_versions_id',
        'parent_id',
        'elementable_id',
        'elementable_type',
        'help_text',
        'description',
        'is_repeatable',
        'repeater_item_label',
        'is_resetable',
        'visible_web',
        'visible_pdf',
        'is_template',
        'form_field_data_bindings_id',
        'order',
    ];

    protected $casts = [
        'is_repeatable' => 'boolean',
        'is_resetable' => 'boolean',
        'visible_web' => 'boolean',
        'visible_pdf' => 'boolean',
        'is_template' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->token)) {
                $model->token = Str::uuid();
            }
        });
    }

    // Polymorphic relationship to the specific element type
    public function elementable(): MorphTo
    {
        return $this->morphTo();
    }

    // Relationship to form version
    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_versions_id');
    }

    // Self-referencing relationship for parent/child hierarchy
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FormElement::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(FormElement::class, 'parent_id')->orderBy('order');
    }

    // Data binding relationship
    public function dataBinding(): BelongsTo
    {
        return $this->belongsTo(FormFieldDataBinding::class, 'form_field_data_bindings_id');
    }

    // Required methods for SolutionForest Tree plugin
    public function determineStatusUsing(): string
    {
        return 'status';
    }

    public function getChildrenKeyName(): string
    {
        return 'parent_id';
    }

    public function getParentKeyName(): string
    {
        return 'id';
    }

    public function getTitleKeyName(): string
    {
        return 'name';
    }

    // Helper method to check if element can have children (only containers)
    public function canHaveChildren(): bool
    {
        return $this->elementable_type === ContainerFormElement::class;
    }

    // Scope to get root elements (no parent)
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    // Scope to get elements by form version
    public function scopeForFormVersion($query, $formVersionId)
    {
        return $query->where('form_versions_id', $formVersionId);
    }
}
