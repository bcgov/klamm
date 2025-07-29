<?php

namespace App\Models\FormMetadata;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\FormBuilding\FormVersion;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FormInterface extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'type',
        'description',
        'form_version_id',
        'label',
        'style',
        'condition',
    ];

    protected static $logAttributes = [
        'type',
        'description',
        'label',
        'style',
        'condition',
    ];


    public function actions(): HasMany
    {
        return $this->hasMany(InterfaceAction::class, 'form_interface_id');
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

    public function formVersions(): BelongsToMany
    {
        return $this->belongsToMany(FormVersion::class, 'form_version_form_interfaces')
            ->withPivot('order')
            ->withTimestamps();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $interfaceName = $this->label ?: 'Unnamed Interface';
                $interfaceType = $this->type ?: 'Unknown Type';

                if ($eventName === 'created') {
                    return "Interface '{$interfaceName}' ({$interfaceType}) was created";
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
                    return "Interface '{$interfaceName}' ({$interfaceType}) had changes to: {$changesStr}";
                }

                return "Interface '{$interfaceName}' ({$interfaceType}) was {$eventName}";
            });
    }

    public function getLogNameToUse(): string
    {
        return 'form_interfaces';
    }
}
