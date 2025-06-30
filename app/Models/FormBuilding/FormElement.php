<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
