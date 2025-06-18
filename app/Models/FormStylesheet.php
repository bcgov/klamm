<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormStylesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'filepath',
        'name',
        'type',
        'description',
        'content',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }
}
