<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormFieldValue extends Model
{
    protected $fillable = ['form_field_id', 'value'];

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }
}
