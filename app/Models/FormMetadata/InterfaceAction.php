<?php

namespace App\Models\FormMetadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class InterfaceAction extends Model
{
    use HasFactory, LogsActivity;

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

    protected static $logAttributes = [
        'label',
        'action_type',
        'type',
        'host',
        'path',
        'authentication',
        'order',
        'headers',
        'body',
        'parameters',
    ];

    public function formInterface(): BelongsTo
    {
        return $this->belongsTo(FormInterface::class, 'form_interface_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $actionName = $this->label ?: 'Unnamed Action';
                $actionType = $this->action_type ?: 'Unknown Type';
                $interfaceName = $this->formInterface ? $this->formInterface->label : 'Unknown Interface';

                if ($eventName === 'created') {
                    return "Action '{$actionName}' ({$actionType}) was created for interface '{$interfaceName}'";
                }

                $changes = array_keys($this->getDirty());
                $changes = array_filter($changes, function ($change) {
                    return !in_array($change, ['updated_at']);
                });

                if (!empty($changes)) {
                    $changes = array_map(function ($change) {
                        return str_replace('_', ' ', $change);
                    }, $changes);

                    $changesStr = implode(', ', array_unique($changes));
                    return "Action '{$actionName}' ({$actionType}) had changes to: {$changesStr} for interface '{$interfaceName}'";
                }

                return "Action '{$actionName}' ({$actionType}) was {$eventName} for interface '{$interfaceName}'";
            });
    }

    public function getLogNameToUse(): string
    {
        return 'interface_actions';
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
