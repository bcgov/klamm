<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormFieldValidation extends Model
{
    protected $fillable = ['form_field_id', 'type', 'value', 'error_message'];

    public function formField()
    {
        return $this->belongsTo(FormField::class);
    }
}
