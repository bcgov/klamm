<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInstanceFieldValidation extends Model
{
    protected $fillable = ['form_instance_field_id', 'type', 'value', 'error_message'];

    public function formInstanceField(): BelongsTo
    {
        return $this->belongsTo(FormInstanceField::class);
    }
}
