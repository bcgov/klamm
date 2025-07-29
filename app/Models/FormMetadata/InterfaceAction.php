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
        'order',
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

    public static function actionTypes(): array
    {
        return self::query()
            ->distinct()
            ->whereNotNull('action_type')
            ->where('action_type', '!=', '')
            ->pluck('action_type')
            ->filter()
            ->sort()
            ->values()
            ->mapWithKeys(fn($type) => [$type => $type])
            ->toArray();
    }

    public static function types(): array
    {
        return self::query()
            ->distinct()
            ->whereNotNull('type')
            ->where('type', '!=', '')
            ->pluck('type')
            ->filter()
            ->sort()
            ->values()
            ->mapWithKeys(fn($type) => [$type => $type])
            ->toArray();
    }
}
