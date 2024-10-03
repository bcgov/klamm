<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldGroupInstance extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'field_group_id',
        'label',
        'repeater',
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
}
