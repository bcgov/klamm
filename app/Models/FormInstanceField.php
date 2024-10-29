<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormInstanceField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'order',
        'form_field_id',
        'label',
        'data_binding',
        'styles',
        'conditional_logic',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function formField(): BelongsTo
    {
        return $this->belongsTo(FormField::class);
    }

    public function fieldGroupInstance(): BelongsTo
    {
        return $this->belongsTo(FieldGroupInstance::class);
    }


    public function validations()
    {
        return $this->hasMany(FormInstanceFieldValidation::class);
    }
}
