<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFieldDateFormat extends Model
{
    protected $fillable = ['form_field_id', 'date_format'];

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
