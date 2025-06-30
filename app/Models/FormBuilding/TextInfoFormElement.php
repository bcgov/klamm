<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class TextInfoFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
    ];

    /**
     * Get the form element that owns this text info element.
     */
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
