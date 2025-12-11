<?php

namespace App\Jobs;

use App\Models\ChangeTicket;
use App\Models\Anonymizer\AnonymousUpload;
use App\Models\Anonymizer\AnonymousSiebelStaging;
use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\AnonymizationJobs;
use App\Models\Anonymizer\AnonymousSiebelColumnDependency;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
    }

    public function handle()
    {
        try {
            $upload = AnonymousUpload::find($this->uploadId);
            if (! $upload) {
                Log::warning('GenerateChangeTicketsFromUpload: upload not found', ['upload_id' => $this->uploadId]);
                return;
            }

            Log::info('GenerateChangeTicketsFromUpload: start', ['upload_id' => $this->uploadId]);

            // Skip ticket creation for the first successful import to avoid noise on initial catalog load
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

            // Column-level changes captured post-sync using catalog changed_at/changed_fields
            AnonymousSiebelColumn::query()
                ->join('anonymous_siebel_tables as t', 't.id', '=', 'anonymous_siebel_columns.table_id')
                ->join('anonymous_siebel_schemas as s', 's.id', '=', 't.schema_id')
                ->whereNotNull('anonymous_siebel_columns.changed_at')
                ->whereNotNull('anonymous_siebel_columns.changed_fields')
                ->where('anonymous_siebel_columns.changed_at', '>=', $windowStart)
                ->where('anonymous_siebel_columns.last_synced_at', '>=', $windowStart)
                ->orderBy('anonymous_siebel_columns.id')
                ->chunk(500, function ($changedColumns) use ($upload, &$changedCount) {
                    foreach ($changedColumns as $col) {
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

                        ChangeTicket::create([
                            'title' => $title,
                            'status' => 'open',
                            'priority' => 'normal',
                            'scope_type' => 'column',
                            'scope_name' => $scopeName,
                            'impact_summary' => 'Column definition changed in latest upload. Review anonymization method association.',
                            'diff_payload' => $col->changed_fields,
                            'upload_id' => $upload->id,
                        ]);
                        Log::info('GenerateChangeTicketsFromUpload: created ticket (changed column)', ['scope' => $scopeName]);
                        $changedCount++;
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
                    ->chunk(1000, function ($catalogColumns) use ($upload, &$deletedCount) {
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
                ->chunk(500, function ($tables) use ($upload, &$changedCount) {
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
                            'scope_type' => 'table',
                            'scope_name' => $scopeName,
                            'impact_summary' => 'Table attributes changed compared to catalog. Review anonymization methods and jobs.',
                            'diff_payload' => $table->changed_fields,
                            'upload_id' => $upload->id,
                        ]);
                        Log::info('GenerateChangeTicketsFromUpload: created ticket (table change)', ['scope' => $scopeName]);
                        $changedCount++;
                    }
                });

            // Update upload status_detail with counts
            $upload->status_detail = sprintf('Tickets created: %d new, %d deleted, %d changed', $newCount, $deletedCount, $changedCount);
            $upload->progress_updated_at = now();
            $upload->save();

            Log::info('GenerateChangeTicketsFromUpload: finished', [
                'upload_id' => $this->uploadId,
                'new' => $newCount,
                'deleted' => $deletedCount,
                'changed' => $changedCount,
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateChangeTicketsFromUpload: error', [
                'upload_id' => $this->uploadId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // let the worker mark it failed, but now we have details
        }
    }
}
