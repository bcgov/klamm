<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithAnonymousSiebelSync;
use App\Models\Anonymizer\AnonymousUpload;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Job that streams anonymized Siebel metadata and synchronizes it in a single pass.
 */
class SyncAnonymousSiebelColumnsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use InteractsWithAnonymousSiebelSync;

    public function __construct(public int $uploadId) {}

    public int $timeout = 600;

    /**
     * Streams the upload and performs the sync in a single pass.
     */
    public function handle(): void
    {
        $upload = AnonymousUpload::findOrFail($this->uploadId);

        $runAt = CarbonImmutable::now();
        $totalBytes = $this->determineUploadSize($upload);

        $upload->update([
            'status' => 'processing',
            'status_detail' => 'Preparing import',
            'error' => null,
            'inserted' => 0,
            'updated' => 0,
            'deleted' => 0,
            'total_bytes' => $totalBytes,
            'processed_bytes' => 0,
            'processed_rows' => 0,
            'progress_updated_at' => $runAt,
        ]);

        try {
            $runTimestamp = $runAt->toIso8601String();

            $result = $this->processUploadStream($upload, $runTimestamp, $runAt, $totalBytes);

            $this->persistProgress($upload->id, [
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'status_detail' => 'Reconciling deletions',
            ]);

            $deletedCount = $this->softDeleteMissingColumns($result['touchedTableIdentities'], $result['processedColumnIdentities'], $runAt);

            if ($deletedCount > 0) {
                $result['totals']['deleted'] = $deletedCount;
            }

            $this->persistProgress($upload->id, [
                'deleted' => $result['totals']['deleted'] ?? 0,
                'status_detail' => 'Import completed',
            ]);

            $upload->update([
                'status' => 'completed',
                'status_detail' => 'Import completed',
                'error' => null,
                'inserted' => $result['totals']['inserted'],
                'updated' => $result['totals']['updated'],
                'deleted' => $result['totals']['deleted'] ?? 0,
                'processed_rows' => $result['processedRows'],
                'processed_bytes' => $result['processedBytes'],
                'progress_updated_at' => CarbonImmutable::now(),
            ]);
        } catch (Throwable $exception) {
            $upload->update([
                'status' => 'failed',
                'status_detail' => 'Failed',
                'error' => $exception->getMessage(),
                'progress_updated_at' => CarbonImmutable::now(),
            ]);

            throw $exception;
        }
    }

    /**
     * Stream the uploaded CSV and synchronize columns on the fly.
     */
    protected function processUploadStream(AnonymousUpload $upload, string $runTimestamp, CarbonImmutable $runAt, ?int $totalBytes): array
    {
        $stream = Storage::disk($upload->file_disk)->readStream($upload->path);
        if (! $stream) {
            throw new RuntimeException('Unable to open upload stream');
        }

        $header = $this->readHeader($stream);
        if ($header === null) {
            fclose($stream);

            throw new RuntimeException('The uploaded CSV did not contain a header row.');
        }

        $totals = [
            'inserted' => 0,
            'updated' => 0,
        ];

        $databaseCache = [];
        $schemaCache = [];
        $tableCache = [];
        $dataTypeCache = [];
        $touchedTableIdentities = [];
        $processedColumnIdentities = [];

        $processedRows = 0;
        $processedBytes = 0;
        $lastStatusDetail = 'Processing rows';
        $lastProgressPingAt = microtime(true);
        $columnCache = [];
        $duplicateSkips = 0;

        $this->persistProgress($upload->id, [
            'status_detail' => $lastStatusDetail,
            'processed_rows' => 0,
            'processed_bytes' => 0,
            'inserted' => 0,
            'updated' => 0,
        ]);

        while (($row = fgetcsv($stream)) !== false) {
            if ($row === null || $row === [null] || $row === ['']) {
                continue;
            }

            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = $row[$index] ?? null;
            }

            $payload = $this->buildRowPayload($assoc, $runTimestamp);

            if ($payload === null) {
                continue;
            }

            $processedRows++;
            $processedBytes = $this->streamOffset($stream, $processedBytes);

            $databaseId = $this->resolveDatabaseId($payload['database_name'], $runAt, $databaseCache);

            $schemaCache[$databaseId] ??= [];
            $schemaId = $this->resolveSchemaId($databaseId, $payload['schema_name'], $runAt, $schemaCache[$databaseId]);

            $tableCache[$schemaId] ??= [];
            $tableId = $this->resolveTableId(
                $schemaId,
                $payload['table_name'],
                $payload['object_type'],
                $payload['table_comment'],
                $runAt,
                $tableCache[$schemaId]
            );

            $tableIdentityKey = $this->tableIdentityKey($databaseId, $payload['schema_name'], $payload['table_name']);
            $columnIdentityKey = $this->columnIdentityKey($databaseId, $payload['schema_name'], $payload['table_name'], $payload['column_name']);

            $touchedTableIdentities[$tableIdentityKey] = [
                'database_id' => $databaseId,
                'schema_name' => $payload['schema_name'],
                'table_name' => $payload['table_name'],
            ];

            if (isset($processedColumnIdentities[$columnIdentityKey])) {
                ++$duplicateSkips;
                logger()->warning('Duplicate column identity detected in Siebel import row; skipping duplicate', [
                    'upload_id' => $upload->id,
                    'column_identity' => $columnIdentityKey,
                ]);
                continue;
            }

            $processedColumnIdentities[$columnIdentityKey] = true;

            $dataTypeId = $this->resolveDataTypeId($payload['data_type'], $runAt, $dataTypeCache);

            $rowObject = (object) $payload;

            $result = $this->upsertColumn($tableId, $dataTypeId, $rowObject, $runAt, $columnCache);

            $totals['inserted'] += $result['inserted'];
            $totals['updated'] += $result['updated'];

            $lastStatusDetail = sprintf(
                'Processing %s.%s → %s',
                $payload['schema_name'],
                $payload['table_name'],
                $payload['column_name']
            );

            if ($this->shouldReportProgress($processedRows, $lastProgressPingAt)) {
                $this->persistProgress($upload->id, [
                    'processed_rows' => $processedRows,
                    'processed_bytes' => $processedBytes,
                    'inserted' => $totals['inserted'],
                    'updated' => $totals['updated'],
                    'status_detail' => $lastStatusDetail,
                ]);

                $lastProgressPingAt = microtime(true);
            }
        }

        fclose($stream);

        if ($processedRows === 0) {
            throw new RuntimeException('The uploaded CSV did not contain any data rows.');
        }

        $this->persistProgress($upload->id, [
            'processed_rows' => $processedRows,
            'processed_bytes' => $totalBytes ? max($processedBytes, $totalBytes) : $processedBytes,
            'inserted' => $totals['inserted'],
            'updated' => $totals['updated'],
            'status_detail' => $lastStatusDetail,
        ]);

        if ($duplicateSkips > 0) {
            logger()->info('Skipped duplicate column identities during Siebel import', [
                'upload_id' => $upload->id,
                'duplicates' => $duplicateSkips,
            ]);
        }

        return [
            'totals' => $totals,
            'touchedTableIdentities' => $touchedTableIdentities,
            'processedColumnIdentities' => $processedColumnIdentities,
            'processedRows' => $processedRows,
            'processedBytes' => $totalBytes ? max($processedBytes, $totalBytes) : $processedBytes,
        ];
    }

    /**
     * Build a normalized payload for a CSV row. Returns null when required data is missing.
     */
    protected function buildRowPayload(array $assoc, string $runTimestamp): ?array
    {
        $databaseName = trim((string) ($assoc['DATABASE_NAME'] ?? ''));
        $schemaName = trim((string) ($assoc['SCHEMA_NAME'] ?? ''));
        $tableName = trim((string) ($assoc['TABLE_NAME'] ?? ''));
        $columnName = trim((string) ($assoc['COLUMN_NAME'] ?? ''));

        if ($databaseName === '' || $schemaName === '' || $tableName === '' || $columnName === '') {
            return null;
        }

        $objectType = strtolower(trim((string) ($assoc['OBJECT_TYPE'] ?? 'table')));

        $payload = [
            'database_name' => $databaseName,
            'schema_name' => $schemaName,
            'object_type' => $objectType === '' ? 'table' : $objectType,
            'table_name' => $tableName,
            'column_name' => $columnName,
            'column_id' => $this->toInt($assoc['COLUMN_ID'] ?? null),
            'data_type' => $this->toNullOrString($assoc['DATA_TYPE'] ?? null),
            'data_length' => $this->toInt($assoc['DATA_LENGTH'] ?? null),
            'data_precision' => $this->toInt($assoc['DATA_PRECISION'] ?? null),
            'data_scale' => $this->toInt($assoc['DATA_SCALE'] ?? null),
            'nullable' => $this->toNullOrString($assoc['NULLABLE'] ?? null),
            'char_length' => $this->toInt($assoc['CHAR_LENGTH'] ?? null),
            'column_comment' => $this->toNullOrString($assoc['COLUMN_COMMENT'] ?? null),
            'table_comment' => $this->toNullOrString($assoc['TABLE_COMMENT'] ?? null),
        ];

        $payload['related_columns_raw'] = null;
        $payload['related_columns'] = null;

        $hashSource = Arr::except($payload, ['related_columns_raw', 'related_columns']);
        $payload['content_hash'] = hash('sha256', json_encode($hashSource, JSON_UNESCAPED_UNICODE));
        $payload['last_synced_at'] = $runTimestamp;
        $payload['created_at'] = $runTimestamp;
        $payload['updated_at'] = $runTimestamp;

        return $payload;
    }

    protected function persistProgress(int $uploadId, array $attributes): void
    {
        $attributes['progress_updated_at'] = $attributes['progress_updated_at'] ?? CarbonImmutable::now();

        AnonymousUpload::whereKey($uploadId)->update($attributes);
    }

    protected function streamOffset($stream, int $previous): int
    {
        $position = ftell($stream);

        if ($position === false) {
            return $previous;
        }

        return max($previous, $position);
    }

    protected function shouldReportProgress(int $processedRows, float $lastReportedAt): bool
    {
        if ($processedRows <= 5) {
            return true;
        }

        if ($processedRows % 500 === 0) {
            return true;
        }

        return (microtime(true) - $lastReportedAt) >= 2.0;
    }

    protected function determineUploadSize(AnonymousUpload $upload): ?int
    {
        try {
            return Storage::disk($upload->file_disk)->size($upload->path);
        } catch (Throwable) {
            return null;
        }
    }
}
