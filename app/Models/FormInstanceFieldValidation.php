<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormInstanceFieldValidation extends Model
{
    protected $fillable = ['form_instance_field_id', 'type', 'value', 'error_message'];

    public function formInstanceField()
    {
        return $this->belongsTo(FormInstanceField::class);
    }
}
