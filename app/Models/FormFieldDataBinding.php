<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormFieldDataBinding extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'data_sources_id',
        'data_binding_path',
        'data_binding_type',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_sources_id');
    }

    public function formElements(): HasMany
    {
        return $this->hasMany(FormElement::class, 'form_field_data_bindings_id');
    }
}
