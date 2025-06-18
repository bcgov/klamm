<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ButtonInputFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'button_type',
    ];

    // Polymorphic relationship back to FormElement
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
