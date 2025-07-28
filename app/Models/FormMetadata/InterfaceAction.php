<?php

namespace App\Models\FormMetadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterfaceAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_interface_id',
        'label',
        'action_type',
        'type',
        'host',
        'path',
        'authentication',
        'headers',
        'body',
        'parameters',
    ];

    protected $casts = [
        'headers' => 'array',
        'body' => 'array',
        'parameters' => 'array',
    ];

    public function formInterface(): BelongsTo
    {
        return $this->belongsTo(FormInterface::class, 'form_interface_id');
    }
}
