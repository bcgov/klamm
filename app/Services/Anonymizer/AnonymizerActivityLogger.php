<?php

namespace App\Services\Anonymizer;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use Illuminate\Support\Facades\DB;

class AnonymizerActivityLogger
{
    /**
     * Buffer for pending activity log entries. Flushed in batches to avoid N+1 queries.
     *
     * @var array<int, array{columnId: int, eventName: string, diff: array, context: array}>
     */
    protected static array $pendingEvents = [];

    /**
     * Maximum number of events to buffer before auto-flushing.
     */
    protected static int $batchSize = 500;

    /**
     * Queue a single column event for batch logging.
     * Events are buffered and flushed when the batch size is reached.
     */
    public static function logColumnEvent(int $columnId, string $eventName, array $diff = [], array $context = []): void
    {
        self::$pendingEvents[] = [
            'columnId' => $columnId,
            'eventName' => $eventName,
            'diff' => $diff,
            'context' => $context,
        ];

        if (count(self::$pendingEvents) >= self::$batchSize) {
            self::flush();
        }
    }

    /**
     * Flush all pending activity log events in a single batch.
     * Loads all referenced columns in one eager-loaded query, then bulk-inserts activity log rows.
     */
    public static function flush(): void
    {
        if (self::$pendingEvents === []) {
            return;
        }

        $events = self::$pendingEvents;
        self::$pendingEvents = [];

        // Collect unique column IDs and load them all in a single query
        $columnIds = array_unique(array_column($events, 'columnId'));

        $columns = AnonymousSiebelColumn::withTrashed()
            ->with([
                'table' => fn($query) => $query->withTrashed()->with([
                    'schema' => fn($schemaQuery) => $schemaQuery->withTrashed()->with([
                        'database' => fn($databaseQuery) => $databaseQuery->withTrashed(),
                    ]),
                ]),
                'dataType' => fn($query) => $query->withTrashed(),
            ])
            ->whereIn('id', $columnIds)
            ->get()
            ->keyBy('id');

        // Build bulk insert rows for the activity_log table
        $logRows = [];
        $logName = AnonymousSiebelColumn::activityLogName();

        foreach ($events as $event) {
            $column = $columns->get($event['columnId']);

            if (! $column) {
                continue;
            }

            $properties = [
                'attributes' => self::extractAttributes($column),
                'old' => self::extractOldValues($event['diff']),
                'diff' => $event['diff'],
                'diff_fields' => array_keys($event['diff']),
                'source' => 'anonymous_upload',
            ];

            if ($event['context'] !== []) {
                $properties = array_merge($properties, array_filter($event['context'], fn($value) => $value !== null));
            }

            $description = $column->makeActivityDescription($event['eventName'], [
                'diff' => $event['diff'],
                'context' => $event['context'],
            ]);

            $logRows[] = [
                'log_name' => $logName,
                'description' => $description,
                'subject_type' => $column->getMorphClass(),
                'subject_id' => $column->getKey(),
                'causer_type' => null,
                'causer_id' => null,
                'event' => $event['eventName'],
                'properties' => json_encode($properties, JSON_UNESCAPED_UNICODE),
                'batch_uuid' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Bulk insert all activity log rows in chunks to avoid query size limits
        if ($logRows !== []) {
            foreach (array_chunk($logRows, 500) as $chunk) {
                DB::table('activity_log')->insert($chunk);
            }
        }
    }

    /**
     * Get the number of pending (unflushed) events.
     */
    public static function pendingCount(): int
    {
        return count(self::$pendingEvents);
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
