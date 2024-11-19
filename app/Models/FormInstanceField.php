<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormInstanceField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'form_field_id',
        'order',
        'label',
        'data_binding_path',
        'data_binding',
        'conditional_logic',
        'styles',
        'field_group_instance_id',
        'custom_id'
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

    public function validations(): HasMany
    {
        return $this->hasMany(FormInstanceFieldValidation::class);
    }
    
}
