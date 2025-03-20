<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SiebelField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'business_component_id',
        'table_id',
        'table_column',
        'multi_value_link',
        'multi_value_link_field',
        'join',
        'join_column',
        'calculated_value',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'has_field_references' => 'boolean',
        'is_referenced' => 'boolean',
        'has_list_of_values' => 'boolean',
    ];

    protected $appends = [
        'is_referenced',
        'has_field_references',
        'has_list_of_values',
    ];

    /**
     * Get whether this field has any references in the calculated value.
     */
    protected function hasFieldReferences(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->references()->exists(),
        );
    }

    /**
     * Get whether this field is referenced by another field's calculated value.
     */
    protected function isReferenced(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->referencedBy()->exists(),
        );
    }

    /**
     * Get whether this field references any Values from the Siebel List of Values (LOV).
     */
    protected function hasListOfValues(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->values()->exists(),
        );
    }

    /**
     * Get the table that owns the field.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }

    /**
     * Get the business component that owns the field.
     */
    public function businessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    /**
     * Get the fields that reference this field in the calculated value.
     */
    public function referencedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            SiebelField::class,
            'siebel_field_references',
            'referenced_field_id',
            'parent_field_id'
        )->withTimestamps();
    }

    /**
     * Get the fields that this field references in the calculated value.
     */
    public function references(): BelongsToMany
    {
        return $this->belongsToMany(
            SiebelField::class,
            'siebel_field_references',
            'parent_field_id',
            'referenced_field_id'
        )->withTimestamps();
    }

    /**
     * Get the values associated with this field in the calculated value.
     */
    public function values(): BelongsToMany
    {
        return $this->belongsToMany(SiebelValue::class, 'siebel_field_values')
            ->withTimestamps();
    }
}
