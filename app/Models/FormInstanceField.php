<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FormInstanceField extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'form_field_id',
        'order',
        'custom_label',
        'customize_label',
        'custom_data_binding_path',
        'custom_data_binding',
        'custom_help_text',
        'custom_mask',
        'field_group_instance_id',
        'container_id',
        'instance_id',
        'custom_instance_id'
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

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function styleInstances(): HasMany
    {
        return $this->hasMany(StyleInstance::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(FormInstanceFieldValidation::class);
    }

    public function conditionals(): HasMany
    {
        return $this->hasMany(FormInstanceFieldConditionals::class);
    }

    public function formInstanceFieldValue(): HasOne
    {
        return $this->hasOne(FormInstanceFieldValue::class);
    }
}
