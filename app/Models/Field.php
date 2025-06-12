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

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->type = 'field';
    }

    public function newQuery()
    {
        return parent::newQuery()->where('type', 'field');
    }

    public static function create(array $attributes = [])
    {
        $attributes['type'] = 'field';
        return static::query()->create($attributes);
    }

    public function getFieldAttributes()
    {
        return [
            'field_template_id' => $this->field_template_id,
            'custom_mask' => $this->custom_mask,
        ];
    }

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class);
    }

    public function elementValue(): HasOne
    {
        return $this->hasOne(ElementValue::class, 'element_id');
    }

    public function elementDateFormat(): HasOne
    {
        return $this->hasOne(ElementDateFormat::class, 'element_id');
    }

    public function selectOptionInstances(): HasMany
    {
        return $this->hasMany(SelectOptionInstance::class, 'element_id');
    }
}
