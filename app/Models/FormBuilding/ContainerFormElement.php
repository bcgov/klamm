<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ContainerFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'container_type',
        'collapsible',
        'collapsed_by_default',
        'is_repeatable',
        'legend',
    ];

    protected $casts = [
        'collapsible' => 'boolean',
        'collapsed_by_default' => 'boolean',
        'is_repeatable' => 'boolean',
    ];

    /**
     * Get the form element that owns this container element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }

    /**
     * Get available container types.
     */
    public static function getContainerTypes(): array
    {
        return [
            'page' => 'Page',
            'fieldset' => 'Fieldset',
            'section' => 'Section',
        ];
    }

    /**
     * Check if this container can have children.
     */
    public function canHaveChildren(): bool
    {
        return in_array($this->container_type, ['page', 'fieldset', 'section']);
    }
}
