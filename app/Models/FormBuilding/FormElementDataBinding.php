<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\FormMetadata\FormDataSource;

class FormElementDataBinding extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'form_element_id',
        'form_data_source_id',
        'path',
        'order',
        'condition',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function formElement(): BelongsTo
    {
        return $this->belongsTo(FormElement::class);
    }

    public function formDataSource(): BelongsTo
    {
        return $this->belongsTo(FormDataSource::class);
    }
}
