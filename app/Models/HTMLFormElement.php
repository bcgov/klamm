<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class HTMLFormElement extends Model
{
    use HasFactory;

    protected $table = 'html_form_elements';

    protected $fillable = [
        'name',
        'html_content',
        'repeater_item_label',
    ];

    // Polymorphic relationship back to FormElement
    public function formElement(): MorphOne
    {
        return $this->morphOne(FormElement::class, 'elementable');
    }
}
