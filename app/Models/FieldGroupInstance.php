<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldGroupInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'field_group_id',
        'label',
        'customize_label',
        'custom_repeater_item_label',
        'repeater',
        'custom_data_binding_path',
        'custom_data_binding',
        'visibility',
        'order',
        'instance_id',
        'custom_instance_id',

    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function fieldGroup(): BelongsTo
    {
        return $this->belongsTo(FieldGroup::class);
    }

    public function formInstanceFields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class);
    }

    public function styleInstances(): HasMany
    {
        return $this->hasMany(StyleInstance::class);
    }
}
