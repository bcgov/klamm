<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormFieldValue extends Model
{
    protected $fillable = ['form_field_id', 'value'];

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    } 
}
