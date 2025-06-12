<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SelectOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_template_id',
        'name',
        'label',
        'value',
        'description',
    ];

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class);
    }

    public function selectOptionInstances(): HasMany
    {
        return $this->hasMany(SelectOptionInstance::class);
    }
}
