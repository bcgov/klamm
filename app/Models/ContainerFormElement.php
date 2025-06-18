<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'collapsible' => 'boolean',
        'collapsed_by_default' => 'boolean',
    ];

    // Polymorphic relationship back to FormElement
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
