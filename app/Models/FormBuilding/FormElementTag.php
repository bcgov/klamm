<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FormElementTag extends Model
{
    use HasFactory;

    protected $table = 'form_element_tags';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the form elements that have this tag.
     */
    public function formElements(): BelongsToMany
    {
        return $this->belongsToMany(FormElement::class, 'form_element_form_element_tag');
    }
}
