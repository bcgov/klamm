<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Field extends Element
{
    use HasFactory;

    protected $fillable = [
        'element_id',
        'field_template_id',
        'custom_mask',
    ];

    public function element(): BelongsTo
    {
        return $this->belongsTo(Element::class);
    }

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class);
    }

    public function elementValue(): HasOne
    {
        return $this->hasOne(ElementValue::class);
    }

    public function elementDateFormat(): HasOne
    {
        return $this->hasOne(ElementDateFormat::class);
    }

    public function selectOptionInstances(): HasMany
    {
        return $this->hasMany(SelectOptionInstance::class);
    }
}
