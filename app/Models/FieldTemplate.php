<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FieldTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'help_text',
        'data_type_id',
        'description',
        'data_binding_path',
        'data_binding',
        'mask'
    ];

    public function dataType(): BelongsTo
    {
        return $this->belongsTo(DataType::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(Field::class);
    }

    public function fieldValidations(): HasMany
    {
        return $this->hasMany(FieldValidation::class);
    }

    public function fieldValue(): HasOne
    {
        return $this->hasOne(FieldValue::class);
    }

    public function fieldDateFormat(): HasOne
    {
        return $this->hasOne(FieldDateFormat::class);
    }

    public function selectOptions(): HasMany
    {
        return $this->hasMany(SelectOption::class);
    }
}
