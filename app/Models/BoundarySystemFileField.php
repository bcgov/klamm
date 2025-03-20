<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoundarySystemFileField extends Model
{
    protected $fillable = [
        'field_name',
        'field_type',
        'field_length',
        'field_description',
        'validations',
    ];

    public function boundarySystemFileFieldType(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFileFieldType::class);
    }
}
