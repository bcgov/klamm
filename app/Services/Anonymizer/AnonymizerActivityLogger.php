<?php

namespace App\Services\Anonymizer;

use App\Models\Anonymizer\AnonymousSiebelColumn;

class AnonymizerActivityLogger
{
    public static function logColumnEvent(int $columnId, string $eventName, array $diff = [], array $context = []): void
    {
        $column = AnonymousSiebelColumn::withTrashed()
            ->with([
                'table' => fn($query) => $query->withTrashed()->with([
                    'schema' => fn($schemaQuery) => $schemaQuery->withTrashed()->with([
                        'database' => fn($databaseQuery) => $databaseQuery->withTrashed(),
                    ]),
                ]),
                'dataType' => fn($query) => $query->withTrashed(),
            ])
            ->find($columnId);

        if (! $column) {
            return;
        }

        $properties = [
            'attributes' => self::extractAttributes($column),
            'old' => self::extractOldValues($diff),
            'diff' => $diff,
            'diff_fields' => array_keys($diff),
            'source' => 'anonymous_upload',
        ];

        if ($context !== []) {
            $properties = array_merge($properties, array_filter($context, fn($value) => $value !== null));
        }

        $description = $column->makeActivityDescription($eventName, [
            'diff' => $diff,
            'context' => $context,
        ]);

        activity($column::activityLogName())
            ->performedOn($column)
            ->event($eventName)
            ->withProperties($properties)
            ->log($description);
    }

    private static function extractAttributes(AnonymousSiebelColumn $column): array
    {
        $keys = $column::activityLogAttributes();
        $attributes = [];

        foreach ($keys as $key) {
            $attributes[$key] = $column->getAttribute($key);
        }

        return $attributes;
    }

    private static function extractOldValues(array $diff): array
    {
        $old = [];

        foreach ($diff as $field => $changes) {
            $old[$field] = $changes['old'] ?? null;
        }

        return $old;
    }
}
