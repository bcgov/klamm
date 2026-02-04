<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsAnonymizerActivity
{
    use LogsActivity;

    public static function activityLogName(): string
    {
        $override = static::activityLogNameOverride();
        if ($override !== null) {
            return $override;
        }

        return Str::of(class_basename(static::class))->snake()->value();
    }

    public static function activityLogAttributes(): array
    {
        $override = static::activityLogAttributesOverride();
        if ($override !== null) {
            return $override;
        }

        $instance = new static();

        $fillable = $instance->getFillable();
        if ($fillable !== []) {
            return $fillable;
        }

        return array_keys($instance->getAttributes());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(static::activityLogAttributes())
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->useLogName(static::activityLogName())
            ->setDescriptionForEvent(fn(string $eventName) => $this->describeActivityEvent($eventName));
    }

    public function makeActivityDescription(string $eventName, array $context = []): string
    {
        return $this->describeActivityEvent($eventName, $context);
    }

    protected static function activityLogNameOverride(): ?string
    {
        return null;
    }

    protected static function activityLogAttributesOverride(): ?array
    {
        return null;
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return null;
    }

    protected function defaultActivityDescription(string $eventName, array $context = []): string
    {
        $modelLabel = Str::of(class_basename($this))->headline();
        $identifier = $this->activityLogSubjectIdentifier();
        $subject = $identifier ? $modelLabel . ' ' . $identifier : $modelLabel;

        return match ($eventName) {
            'created' => $subject . ' created',
            'updated' => $subject . ' updated',
            'deleted' => $subject . ' deleted',
            'restored' => $subject . ' restored',
            default => $subject . ' ' . $eventName,
        };
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        return $this->defaultActivityDescription($eventName, $context);
    }
}
