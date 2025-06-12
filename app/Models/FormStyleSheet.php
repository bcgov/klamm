<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormStyleSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'path',
        'name',
        'type',
        'description',
        'order',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }
}
