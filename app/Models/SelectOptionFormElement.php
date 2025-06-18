<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SelectOptionFormElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'select_input_form_elements_id',
        'label',
        'value',
        'order',
        'description',
    ];

    public function selectInputFormElement(): BelongsTo
    {
        return $this->belongsTo(SelectInputFormElement::class, 'select_input_form_elements_id');
    }
}
