<?php

namespace App\Jobs;

use App\Models\Anonymizer\ChangeTicket;
use App\Models\Anonymizer\AnonymousUpload;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateChangeTicketsFromUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $uploadId;
    public int $tries = 1;
    public int $timeout = 30;

    public function __construct($uploadId)
    {
        $this->uploadId = $uploadId;
        // Use dedicated queue for anonymization work
        $this->onQueue('anonymization');
    }

    public function handle()
    {
        try {
            $upload = AnonymousUpload::find($this->uploadId);
            if (! $upload) {
                Log::warning('GenerateChangeTicketsFromUpload: upload not found', ['upload_id' => $this->uploadId]);
                return;
            }

            if ($upload->create_change_tickets === false) {
                Log::info('GenerateChangeTicketsFromUpload: skipped (disabled for upload)', ['upload_id' => $this->uploadId]);
                return;
            }

            Log::info('GenerateChangeTicketsFromUpload: start', ['upload_id' => $this->uploadId]);

            // Skip ticket creation for the first successful import
            $hasPriorCompletedUpload = AnonymousUpload::query()
                ->where('status', 'completed')
                ->where('id', '<>', $upload->id)
                ->exists();

            $catalogPreExisted = AnonymousSiebelColumn::query()
                ->where('created_at', '<', $upload->created_at ?? now())
                ->exists();

            if (! $hasPriorCompletedUpload && ! $catalogPreExisted) {
                $upload->status_detail = 'Initial import detected; change tickets skipped';
                $upload->progress_updated_at = now();
                $upload->save();

                Log::info('GenerateChangeTicketsFromUpload: skipped change ticket creation for first import', ['upload_id' => $this->uploadId]);

                return;
            }

            $fullImport = $upload->import_type === 'full';
            $runStartedAt = $upload->created_at ?? now();

            // Build a time window to attribute catalog changes to this upload
            $windowStart = $runStartedAt;

            // New/changed/deleted counts
            $newCount = 0;
            $deletedCount = 0;
            $changedCount = 0;
            $urgentAlertCount = 0;

            // Column-level changes captured post-sync using catalog changed_at/changed_fields
            AnonymousSiebelColumn::query()
                ->join('anonymous_siebel_tables as t', 't.id', '=', 'anonymous_siebel_columns.table_id')
                ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
                ->whereNotNull('anonymous_siebel_columns.changed_at')
                ->whereNotNull('anonymous_siebel_columns.changed_fields')
                ->where('anonymous_siebel_columns.changed_at', '>=', $windowStart)
                ->where('anonymous_siebel_columns.last_synced_at', '>=', $windowStart)
                ->orderBy('anonymous_siebel_columns.id')
                ->chunk(500, function ($changedColumns) use ($upload, &$changedCount, &$urgentAlertCount) {
                    foreach ($changedColumns as $col) {
                        $hasJobDependency = DB::table('anonymization_job_columns')
                            ->where('column_id', $col->id)
                            ->exists();

                        $scopeName = "{$col->schema_name}.{$col->table_name}.{$col->column_name}";
                        $title = "Column changed: {$scopeName}";

                        $exists = ChangeTicket::query()
                            ->where('upload_id', $upload->id)
                            ->where('scope_type', 'column')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        $existsGlobal = ChangeTicket::query()
                            ->where('scope_type', 'column')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->whereIn('status', ['open', 'in_progress'])
                            ->exists();
                        if ($existsGlobal) {
                            continue;
                        }

                        $severity = $this->severityForChangedColumn($col->changed_fields, $hasJobDependency);
                        $priority = $hasJobDependency ? 'high' : 'normal';

                        ChangeTicket::create([
                            'title' => $title,
                            'status' => 'open',
                            'priority' => $priority,
                            'severity' => $severity,
                            'scope_type' => 'column',
                            'scope_name' => $scopeName,
                            'impact_summary' => $hasJobDependency
                                ? 'Column definition changed and is referenced by anonymization jobs. Review jobs/methods for breakage.'
                                : 'Column definition changed in latest upload. Review anonymization method association.',
                            'diff_payload' => $this->diffPayloadAsJsonString($col->changed_fields),
                            'upload_id' => $upload->id,
                        ]);
                        Log::info('GenerateChangeTicketsFromUpload: created ticket (changed column)', ['scope' => $scopeName]);
                        $changedCount++;

                        // Emit a separate URGENT alert ticket only when the change looks breaking and a job explicitly references the column.
                        if ($hasJobDependency && $this->diffIndicatesBreakingChange($col->changed_fields)) {
                            $urgentTitle = "URGENT: Job dependency risk - Column changed: {$scopeName}";
                            $existsUrgent = ChangeTicket::query()
                                ->where('scope_type', 'column')
                                ->where('scope_name', $scopeName)
                                ->where('title', $urgentTitle)
                                ->whereIn('status', ['open', 'in_progress'])
                                ->exists();

                            if (! $existsUrgent) {
                                ChangeTicket::create([
                                    'title' => $urgentTitle,
                                    'status' => 'open',
                                    'priority' => 'high',
                                    'severity' => 'high',
                                    'scope_type' => 'column',
                                    'scope_name' => $scopeName,
                                    'impact_summary' => 'Breaking-ish schema/shape change detected on a column referenced by anonymization jobs. Regenerate and review job SQL before execution.',
                                    'diff_payload' => $this->diffPayloadAsJsonString($col->changed_fields),
                                    'upload_id' => $upload->id,
                                ]);
                                Log::info('GenerateChangeTicketsFromUpload: created URGENT alert (job dependency / changed column)', ['scope' => $scopeName]);
                                $urgentAlertCount++;
                            }
                        }
                    }
                });

            // New columns: catalog rows created in this upload window
            AnonymousSiebelColumn::query()
                ->join('anonymous_siebel_tables as t', 't.id', '=', 'anonymous_siebel_columns.table_id')
                ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
                ->where('anonymous_siebel_columns.created_at', '>=', $windowStart)
                ->where('anonymous_siebel_columns.last_synced_at', '>=', $windowStart)
                ->orderBy('anonymous_siebel_columns.id')
                ->chunk(500, function ($newColumns) use ($upload, &$newCount) {
                    foreach ($newColumns as $col) {
                        $scopeName = "{$col->schema_name}.{$col->table_name}.{$col->column_name}";
                        $title = "New column: {$scopeName}";
                        $exists = ChangeTicket::query()
                            ->where('upload_id', $upload->id)
                            ->where('scope_type', 'column')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->exists();
                        if ($exists) {
                            continue;
                        }
                        // Global dedupe: if any unresolved ticket exists for this scope, skip
                        $existsGlobal = ChangeTicket::query()
                            ->where('scope_type', 'column')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->whereIn('status', ['open', 'in_progress'])
                            ->exists();
                        if ($existsGlobal) {
                            continue;
                        }
                        ChangeTicket::create([
                            'title' => $title,
                            'status' => 'open',
                            'priority' => 'normal',
                            'severity' => 'low',
                            'scope_type' => 'column',
                            'scope_name' => $scopeName,
                            'impact_summary' => 'Column was added in this upload. Review anonymization method association.',
                            'diff_payload' => json_encode([
                                'catalog' => Arr::only($col->toArray(), [
                                    'id',
                                    'column_name',
                                    'data_length',
                                    'data_precision',
                                    'data_scale',
                                    'nullable',
                                    'char_length',
                                    'column_comment',
                                ]),
                            ]),
                            'upload_id' => $upload->id,
                        ]);
                        Log::info('GenerateChangeTicketsFromUpload: created ticket (new column)', ['scope' => $scopeName]);
                        $newCount++;
                    }
                });

            // Deleted columns (only on full imports to avoid false positives from partial uploads)
            if ($fullImport) {
                AnonymousSiebelColumn::query()
                    ->select('anonymous_siebel_columns.*', 't.table_name', 's.schema_name')
                    ->join('anonymous_siebel_tables as t', 't.id', '=', 'anonymous_siebel_columns.table_id')
                    ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
                    ->whereNotNull('anonymous_siebel_columns.deleted_at')
                    ->where('anonymous_siebel_columns.deleted_at', '>=', $windowStart)
                    ->orderBy('anonymous_siebel_columns.id')
                    ->chunk(1000, function ($catalogColumns) use ($upload, &$deletedCount, &$urgentAlertCount) {
                        foreach ($catalogColumns as $col) {
                            $hasJobDependency = DB::table('anonymization_job_columns')
                                ->where('column_id', $col->id)
                                ->exists();

                            $scopeName = "{$col->schema_name}.{$col->table_name}.{$col->column_name}";
                            $title = "Deleted column: {$scopeName}";
                            $exists = ChangeTicket::query()
                                ->where('upload_id', $upload->id)
                                ->where('scope_type', 'column')
                                ->where('scope_name', $scopeName)
                                ->where('title', $title)
                                ->exists();
                            if ($exists) {
                                continue;
                            }
                            // Global dedupe
                            $existsGlobal = ChangeTicket::query()
                                ->where('scope_type', 'column')
                                ->where('scope_name', $scopeName)
                                ->where('title', $title)
                                ->whereIn('status', ['open', 'in_progress'])
                                ->exists();
                            if ($existsGlobal) {
                                continue;
                            }

                            ChangeTicket::create([
                                'title' => $title,
                                'status' => 'open',
                                'priority' => $hasJobDependency ? 'high' : 'normal',
                                'severity' => $hasJobDependency ? 'high' : 'medium',
                                'scope_type' => 'column',
                                'scope_name' => $scopeName,
                                'impact_summary' => $hasJobDependency
                                    ? 'Column missing from upload and referenced by anonymization jobs. Review and adjust jobs/methods.'
                                    : 'Column missing from upload compared to catalog; verify removal is expected.',
                                'diff_payload' => json_encode([
                                    'catalog' => Arr::only($col->toArray(), [
                                        'id',
                                        'column_name',
                                        'data_length',
                                        'data_precision',
                                        'data_scale',
                                        'nullable',
                                        'char_length',
                                        'column_comment',
                                    ]),
                                ]),
                                'upload_id' => $upload->id,
                            ]);
                            Log::info('GenerateChangeTicketsFromUpload: created ticket (deleted column)', ['scope' => $scopeName, 'priority' => $hasJobDependency ? 'high' : 'normal']);
                            $deletedCount++;

                            if ($hasJobDependency) {
                                $urgentTitle = "URGENT: Job dependency broken - Column deleted: {$scopeName}";
                                $existsUrgent = ChangeTicket::query()
                                    ->where('scope_type', 'column')
                                    ->where('scope_name', $scopeName)
                                    ->where('title', $urgentTitle)
                                    ->whereIn('status', ['open', 'in_progress'])
                                    ->exists();

                                if (! $existsUrgent) {
                                    ChangeTicket::create([
                                        'title' => $urgentTitle,
                                        'status' => 'open',
                                        'priority' => 'high',
                                        'severity' => 'high',
                                        'scope_type' => 'column',
                                        'scope_name' => $scopeName,
                                        'impact_summary' => 'A column referenced by anonymization jobs was removed from the catalog on a full import. Jobs that include this column will likely generate incomplete SQL until updated.',
                                        'diff_payload' => json_encode([
                                            'catalog' => Arr::only($col->toArray(), [
                                                'id',
                                                'column_name',
                                                'data_length',
                                                'data_precision',
                                                'data_scale',
                                                'nullable',
                                                'char_length',
                                                'column_comment',
                                            ]),
                                        ]),
                                        'upload_id' => $upload->id,
                                    ]);
                                    Log::info('GenerateChangeTicketsFromUpload: created URGENT alert (job dependency / deleted column)', ['scope' => $scopeName]);
                                    $urgentAlertCount++;
                                }
                            }
                        }
                    });
            } else {
                Log::info('GenerateChangeTicketsFromUpload: skipping deleted-column tickets for non-full import', ['upload_id' => $this->uploadId]);
            }

            // Table changes: catalog rows changed in this upload window
            AnonymousSiebelTable::query()
                ->join('anonymous_siebel_schemas as s', 's.id', '=', 'anonymous_siebel_tables.schema_id')
                ->whereNotNull('anonymous_siebel_tables.changed_at')
                ->where('anonymous_siebel_tables.changed_at', '>=', $windowStart)
                ->where('anonymous_siebel_tables.last_synced_at', '>=', $windowStart)
                ->orderBy('anonymous_siebel_tables.id')
                ->chunk(500, function ($tables) use ($upload, &$changedCount, &$urgentAlertCount) {
                    foreach ($tables as $table) {
                        $scopeName = "{$table->schema_name}.{$table->table_name}";
                        $title = "Table definition changed: {$scopeName}";
                        $exists = ChangeTicket::query()
                            ->where('upload_id', $upload->id)
                            ->where('scope_type', 'table')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->exists();
                        if ($exists) {
                            continue;
                        }
                        $existsGlobal = ChangeTicket::query()
                            ->where('scope_type', 'table')
                            ->where('scope_name', $scopeName)
                            ->where('title', $title)
                            ->whereIn('status', ['open', 'in_progress'])
                            ->exists();
                        if ($existsGlobal) {
                            continue;
                        }

                        ChangeTicket::create([
                            'title' => $title,
                            'status' => 'open',
                            'priority' => 'normal',
                            'severity' => 'medium',
                            'scope_type' => 'table',
                            'scope_name' => $scopeName,
                            'impact_summary' => 'Table attributes changed compared to catalog. Review anonymization methods and jobs.',
                            'diff_payload' => $this->diffPayloadAsJsonString($table->changed_fields),
                            'upload_id' => $upload->id,
                        ]);
                        Log::info('GenerateChangeTicketsFromUpload: created ticket (table change)', ['scope' => $scopeName]);
                        $changedCount++;

                        $hasJobDependency = DB::table('anonymization_job_tables')
                            ->where('table_id', $table->id)
                            ->exists()
                            || DB::table('anonymization_job_columns')
                            ->join('anonymous_siebel_columns as c', 'c.id', '=', 'anonymization_job_columns.column_id')
                            ->where('c.table_id', $table->id)
                            ->exists();

                        if ($hasJobDependency) {
                            $urgentTitle = "URGENT: Job dependency risk - Table changed: {$scopeName}";
                            $existsUrgent = ChangeTicket::query()
                                ->where('scope_type', 'table')
                                ->where('scope_name', $scopeName)
                                ->where('title', $urgentTitle)
                                ->whereIn('status', ['open', 'in_progress'])
                                ->exists();

                            if (! $existsUrgent) {
                                ChangeTicket::create([
                                    'title' => $urgentTitle,
                                    'status' => 'open',
                                    'priority' => 'high',
                                    'severity' => 'high',
                                    'scope_type' => 'table',
                                    'scope_name' => $scopeName,
                                    'impact_summary' => 'A table referenced by anonymization jobs changed in the latest catalog sync. Review job scopes and regenerate job SQL before execution.',
                                    'diff_payload' => $this->diffPayloadAsJsonString($table->changed_fields),
                                    'upload_id' => $upload->id,
                                ]);
                                Log::info('GenerateChangeTicketsFromUpload: created URGENT alert (job dependency / table change)', ['scope' => $scopeName]);
                                $urgentAlertCount++;
                            }
                        }
                    }
                });

            // Update upload status_detail with counts
            $upload->status_detail = sprintf(
                'Tickets created: %d new, %d deleted, %d changed; Urgent alerts: %d',
                $newCount,
                $deletedCount,
                $changedCount,
                $urgentAlertCount
            );
            $upload->progress_updated_at = now();
            $upload->save();

            Log::info('GenerateChangeTicketsFromUpload: finished', [
                'upload_id' => $this->uploadId,
                'new' => $newCount,
                'deleted' => $deletedCount,
                'changed' => $changedCount,
                'urgent_alerts' => $urgentAlertCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateChangeTicketsFromUpload: error', [
                'upload_id' => $this->uploadId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // Determine whether a changed_fields payload indicates a likely breaking schema/shape change.
    private function diffIndicatesBreakingChange(array|string|null $diffPayload): bool
    {
        $keys = $this->diffPayloadKeys($diffPayload);
        if ($keys === []) {
            return false;
        }

        $breakingKeys = [
            'deleted_at',
            'data_type_id',
            'data_type',
            'data_length',
            'data_precision',
            'data_scale',
            'char_length',
            'nullable',
            'column_id',
            'related_columns',
            'related_columns_raw',
            'table_id',
        ];

        foreach ($breakingKeys as $key) {
            if (in_array($key, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private function severityForChangedColumn(array|string|null $diffPayload, bool $hasJobDependency): string
    {
        if ($hasJobDependency) {
            return 'high';
        }

        $keys = $this->diffPayloadKeys($diffPayload);
        if ($keys === []) {
            return 'medium';
        }

        // High severity columns with potential breaking schema/shape changes.
        $highKeys = [
            'deleted_at',
            'data_type_id',
            'data_type',
            'data_length',
            'data_precision',
            'data_scale',
            'char_length',
            'nullable',
            'column_id',
            'related_columns',
            'related_columns_raw',
            'table_id',
        ];

        foreach ($highKeys as $k) {
            if (in_array($k, $keys, true)) {
                return 'high';
            }
        }

        // Low severity: documentation-only changes.
        $lowOnly = [
            'column_comment',
            'table_comment',
            'content_hash',
        ];
        $nonLow = array_diff($keys, $lowOnly);
        if ($nonLow === []) {
            return 'low';
        }

        return 'medium';
    }

    private function diffPayloadKeys(array|string|null $diffPayload): array
    {
        if (is_array($diffPayload)) {
            return array_keys($diffPayload);
        }

        if (! is_string($diffPayload) || trim($diffPayload) === '') {
            return [];
        }

        $decoded = json_decode($diffPayload, true);
        if (! is_array($decoded) || $decoded === []) {
            return [];
        }

        return array_keys($decoded);
    }

    private function diffPayloadAsJsonString(array|string|null $diffPayload): ?string
    {
        if ($diffPayload === null) {
            return null;
        }

        if (is_array($diffPayload)) {
            return $diffPayload === [] ? null : json_encode($diffPayload);
        }

        $trimmed = trim($diffPayload);
        return $trimmed === '' ? null : $diffPayload;
    }
}
