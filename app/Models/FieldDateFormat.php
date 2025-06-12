<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldDateFormat extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_template_id',
        'date_format',
    ];

    public function fieldTemplate(): BelongsTo
    {
        return $this->belongsTo(FieldTemplate::class);
    }
}
