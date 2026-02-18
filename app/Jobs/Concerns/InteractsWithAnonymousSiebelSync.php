<?php

namespace App\Jobs\Concerns;

use App\Models\Anonymizer\AnonymousUpload;
use App\Models\Anonymizer\ChangeTicket;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Jobs\Exceptions\AnonymousSiebelCsvValidationException;
use App\Services\Anonymizer\AnonymizerActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use RuntimeException;
use App\Constants\Fodig\Anonymizer\SiebelMetadata;

// Shared utilities for Siebel anonymization synchronization jobs.
trait InteractsWithAnonymousSiebelSync
{
    protected const STAGING_TABLE = 'anonymous_siebel_stagings';
    protected const COLUMNS_TABLE = 'anonymous_siebel_columns';
    protected const TABLES_TABLE = 'anonymous_siebel_tables';
    protected const SCHEMAS_TABLE = 'anonymous_siebel_schemas';
    protected const DATABASES_TABLE = 'anonymous_siebel_databases';
    protected const DATA_TYPES_TABLE = 'anonymous_siebel_data_types';
    protected const DEPENDENCIES_TABLE = 'anonymous_siebel_column_dependencies';
    protected const METADATA_UPSERT_CHUNK_SIZE = 500;

    protected const CSV_VALIDATION_ERROR_CAP = 200;

    protected const CANONICAL_REQUIRED_HEADER_COLUMNS = SiebelMetadata::REQUIRED_HEADER_COLUMNS;

    protected const CANONICAL_OPTIONAL_HEADER_COLUMNS = SiebelMetadata::OPTIONAL_HEADER_COLUMNS;

    protected const TEMP_HEADER_COLUMNS = SiebelMetadata::TEMP_HEADER_COLUMNS;

    protected const TEMP_REQUIRED_HEADER_COLUMNS = [
        'DB_INSTANCE',
        'OWNER',
        'TABLE_NAME',
        'COLUMN_NAME',
        'DATA_TYPE',
    ];


    // Clears previous staging records and streams CSV rows into staging storage.
    protected function ingestToStaging(AnonymousUpload $upload, ?callable $progressReporter = null): int
    {
        DB::table(self::STAGING_TABLE)
            ->where('upload_id', $upload->id)
            ->delete();

        try {
            $errorPath = $this->csvErrorReportPath($upload);
            if ($errorPath && Storage::disk($upload->file_disk)->exists($errorPath)) {
                Storage::disk($upload->file_disk)->delete($errorPath);
            }
        } catch (\Throwable) {
        }

        $stream = Storage::disk($upload->file_disk)->readStream($upload->path);
        if (! $stream) {
            throw new RuntimeException('Unable to open upload stream');
        }

        $header = $this->readHeader($stream);
        if ($header === null) {
            fclose($stream);

            throw new RuntimeException('The uploaded CSV did not contain a header row.');
        }

        $isCanonicalHeader = in_array('DATABASE_NAME', $header, true)
            && in_array('SCHEMA_NAME', $header, true)
            && in_array('TABLE_NAME', $header, true)
            && in_array('COLUMN_NAME', $header, true);
        $isTempHeader = in_array('DB_INSTANCE', $header, true)
            && in_array('OWNER', $header, true)
            && in_array('TABLE_NAME', $header, true)
            && in_array('COLUMN_NAME', $header, true);
        $isSiebelColumnsHeader = in_array('NAME', $header, true)
            && in_array('PARENT TABLE', $header, true);

        $allowedCanonicalHeaders = array_values(array_unique(array_merge(
            self::CANONICAL_REQUIRED_HEADER_COLUMNS,
            self::CANONICAL_OPTIONAL_HEADER_COLUMNS,
        )));
        $allowedTempHeaders = array_values(array_unique(self::TEMP_HEADER_COLUMNS));
        $missingHeaderColumns = [];
        $unknownHeaderColumns = [];
        $detectedFormat = 'unknown';

        if ($isCanonicalHeader) {
            $detectedFormat = 'canonical';
            $missingHeaderColumns = array_values(array_diff(self::CANONICAL_REQUIRED_HEADER_COLUMNS, $header));
            $unknownHeaderColumns = array_values(array_diff($header, $allowedCanonicalHeaders));
        } elseif ($isTempHeader) {
            $detectedFormat = 'temp';
            $missingHeaderColumns = array_values(array_diff(self::TEMP_REQUIRED_HEADER_COLUMNS, $header));
            $unknownHeaderColumns = array_values(array_diff($header, $allowedTempHeaders));
        } elseif ($isSiebelColumnsHeader) {
            $detectedFormat = 'siebel_columns';
            $missingHeaderColumns = [];
            $unknownHeaderColumns = [];
        } else {
            fclose($stream);

            $report = [
                'upload_id' => $upload->id,
                'format' => 'unknown',
                'header' => $header,
                'missing_header_columns' => [],
                'unknown_header_columns' => [],
                'error_cap' => self::CSV_VALIDATION_ERROR_CAP,
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'HEADER',
                        'message' => 'Unrecognized CSV header. Expected canonical Siebel metadata headers (e.g. DATABASE_NAME, SCHEMA_NAME, TABLE_NAME, COLUMN_NAME), temp metadata headers (e.g. DB_INSTANCE, OWNER, TABLE_NAME, COLUMN_NAME), or Siebel column export headers (e.g. NAME, PARENT TABLE).',
                        'value' => null,
                    ],
                ],
                'generated_at' => now()->toIso8601String(),
            ];
            $this->writeCsvErrorReport($upload, $report);

            throw new AnonymousSiebelCsvValidationException('CSV validation failed: unrecognized header format. Download the error report for details.', $report);
        }

        if ($missingHeaderColumns !== [] || $unknownHeaderColumns !== []) {
            fclose($stream);

            $problems = [];
            if ($missingHeaderColumns !== []) {
                $problems[] = 'missing required columns: ' . implode(', ', $missingHeaderColumns);
            }
            if ($unknownHeaderColumns !== []) {
                $problems[] = 'unknown columns: ' . implode(', ', $unknownHeaderColumns);
            }

            $report = [
                'upload_id' => $upload->id,
                'format' => $detectedFormat,
                'header' => $header,
                'missing_header_columns' => $missingHeaderColumns,
                'unknown_header_columns' => $unknownHeaderColumns,
                'error_cap' => self::CSV_VALIDATION_ERROR_CAP,
                'errors' => [
                    [
                        'row' => 1,
                        'field' => 'HEADER',
                        'message' => 'CSV header validation failed (' . implode('; ', $problems) . ').',
                        'value' => null,
                    ],
                ],
                'generated_at' => now()->toIso8601String(),
            ];
            $this->writeCsvErrorReport($upload, $report);

            throw new AnonymousSiebelCsvValidationException('CSV validation failed: invalid header row. Download the error report for details.', $report);
        }
        $siebelIndexMap = [];
        if ($isSiebelColumnsHeader && ! $isCanonicalHeader) {
            foreach ($header as $i => $h) {
                $siebelIndexMap[$h] = $i;
            }
        }

        $tempIndexMap = [];
        if ($isTempHeader) {
            foreach ($header as $i => $h) {
                $tempIndexMap[$h] = $i;
            }
        }

        // Pre-resolve scope context from existing catalog when possible so staging rows have both schema and database.
        $scopeType = $upload->scope_type ?? null;
        $scopeName = $upload->scope_name ?? null;
        $resolvedScope = [
            'database_name' => null,
            'schema_name' => null,
            'table_name' => null,
        ];
        if (is_string($scopeType) && is_string($scopeName) && trim($scopeName) !== '') {
            $normScopeName = $this->norm($scopeName);
            if ($scopeType === 'schema') {
                // Find the database name for this schema
                $record = DB::table(self::SCHEMAS_TABLE . ' as s')
                    ->join(self::DATABASES_TABLE . ' as d', 'd.id', '=', 's.database_id')
                    ->whereRaw('UPPER(TRIM(s.schema_name)) = ?', [$normScopeName])
                    ->select(['d.database_name', 's.schema_name'])
                    ->first();
                if ($record) {
                    $resolvedScope['schema_name'] = (string) $record->schema_name;
                    $resolvedScope['database_name'] = (string) $record->database_name;
                } else {
                    // Fall back to provided name; database will be resolved later if present in CSV
                    $resolvedScope['schema_name'] = $scopeName;
                }
            } elseif ($scopeType === 'table') {
                // Find schema and database for this table
                $record = DB::table(self::TABLES_TABLE . ' as t')
                    ->join(self::SCHEMAS_TABLE . ' as s', 's.id', '=', 't.schema_id')
                    ->join(self::DATABASES_TABLE . ' as d', 'd.id', '=', 's.database_id')
                    ->whereRaw('UPPER(TRIM(t.table_name)) = ?', [$normScopeName])
                    ->select(['d.database_name', 's.schema_name', 't.table_name'])
                    ->first();
                if ($record) {
                    $resolvedScope['table_name'] = (string) $record->table_name;
                    $resolvedScope['schema_name'] = (string) $record->schema_name;
                    $resolvedScope['database_name'] = (string) $record->database_name;
                } else {
                    $resolvedScope['table_name'] = $scopeName;
                }
            } elseif ($scopeType === 'database') {
                $resolvedScope['database_name'] = $scopeName;
            }
        }

        $batch = [];
        $now = now();
        $count = 0;
        $rowNumber = 1;

        $totalBytes = $upload->total_bytes ?? null;
        $dataStart = null;
        $lastProgressAt = microtime(true);
        try {
            $offset = ftell($stream);
            if (is_int($offset) || is_float($offset)) {
                $dataStart = (int) $offset;
            }
        } catch (\Throwable) {
            $dataStart = null;
        }
        $validationErrors = [];
        $validationErrorCount = 0;
        $hasValidationErrors = false;
        $hasValidRows = false;
        $format = $detectedFormat;

        $rules = [
            'DATABASE_NAME' => ['required', 'string', 'max:255'],
            'SCHEMA_NAME' => ['required', 'string', 'max:255'],
            'OBJECT_TYPE' => ['required', 'string', 'max:50'],
            'TABLE_NAME' => ['required', 'string', 'max:255'],
            'COLUMN_NAME' => ['required', 'string', 'max:255'],
            'QUALFIELD' => ['nullable', 'string', 'max:512'],
            'COLUMN_ID' => ['nullable', 'integer', 'min:0'],
            'PR_KEY' => ['nullable', 'string', 'max:32'],
            'REF_TAB_NAME' => ['nullable', 'string', 'max:255'],
            'NUM_DISTINCT' => ['nullable', 'integer', 'min:0'],
            'NUM_NOT_NULL' => ['nullable', 'integer', 'min:0'],
            'NUM_NULLS' => ['nullable', 'integer', 'min:0'],
            'NUM_ROWS' => ['nullable', 'integer', 'min:0'],
            'DATA_TYPE' => ['required', 'string', 'max:255'],
            'DATA_LENGTH' => ['nullable', 'integer', 'min:0'],
            'DATA_PRECISION' => ['nullable', 'integer', 'min:0'],
            'DATA_SCALE' => ['nullable', 'integer', 'min:0'],
            'CHAR_LENGTH' => ['nullable', 'integer', 'min:0'],
            'NULLABLE' => ['nullable', 'string', 'in:Y,N,YES,NO,true,false,TRUE,FALSE,0,1'],
            'ANON_RULE' => ['nullable', 'string', 'max:255'],
            'ANON_NOTE' => ['nullable', 'string'],
            'TABLE_COMMENT' => ['nullable', 'string'],
            'COLUMN_COMMENT' => ['nullable', 'string'],
            'SBL_USER_NAME' => ['nullable', 'string', 'max:255'],
            'SBL_DESC_TEXT' => ['nullable', 'string'],
            'RELATED_COLUMNS' => ['nullable', 'string'],
        ];

        $attributeNames = [
            'DATABASE_NAME' => 'DATABASE_NAME',
            'SCHEMA_NAME' => 'SCHEMA_NAME',
            'OBJECT_TYPE' => 'OBJECT_TYPE',
            'TABLE_NAME' => 'TABLE_NAME',
            'COLUMN_NAME' => 'COLUMN_NAME',
            'QUALFIELD' => 'QUALFIELD',
            'COLUMN_ID' => 'COLUMN_ID',
            'PR_KEY' => 'PR_KEY',
            'REF_TAB_NAME' => 'REF_TAB_NAME',
            'NUM_DISTINCT' => 'NUM_DISTINCT',
            'NUM_NOT_NULL' => 'NUM_NOT_NULL',
            'NUM_NULLS' => 'NUM_NULLS',
            'NUM_ROWS' => 'NUM_ROWS',
            'DATA_TYPE' => 'DATA_TYPE',
            'DATA_LENGTH' => 'DATA_LENGTH',
            'DATA_PRECISION' => 'DATA_PRECISION',
            'DATA_SCALE' => 'DATA_SCALE',
            'CHAR_LENGTH' => 'CHAR_LENGTH',
            'NULLABLE' => 'NULLABLE',
            'ANON_RULE' => 'ANON_RULE',
            'ANON_NOTE' => 'ANON_NOTE',
            'TABLE_COMMENT' => 'TABLE_COMMENT',
            'COLUMN_COMMENT' => 'COLUMN_COMMENT',
            'SBL_USER_NAME' => 'SBL_USER_NAME',
            'SBL_DESC_TEXT' => 'SBL_DESC_TEXT',
            'RELATED_COLUMNS' => 'RELATED_COLUMNS',
        ];

        while (($row = fgetcsv($stream)) !== false) {
            $rowNumber++;
            if ($row === null || $row === [null] || $row === ['']) {
                continue;
            }

            $row = array_map(function ($value) {
                return is_string($value) ? $this->normalizeUtf8String($value, false) : $value;
            }, $row);

            $assoc = [];
            if ($isCanonicalHeader) {
                foreach ($header as $i => $key) {
                    $assoc[$key] = $row[$i] ?? null;
                }
            } elseif ($isTempHeader) {
                $assoc = $this->mapTempRowToCanonicalAssoc($row, $tempIndexMap);
                if (($assoc['TABLE_NAME'] ?? '') === '' || ($assoc['COLUMN_NAME'] ?? '') === '') {
                    continue;
                }

                // Apply upload-provided scope (database/schema/table) with resolved context to ensure catalog matches.
                $assoc['DATABASE_NAME'] = $resolvedScope['database_name'] ?: ($assoc['DATABASE_NAME'] ?: SiebelMetadata::DEFAULT_DATABASE);
                $assoc['SCHEMA_NAME'] = $resolvedScope['schema_name'] ?: ($assoc['SCHEMA_NAME'] ?: SiebelMetadata::DEFAULT_SCHEMA);
                if ($resolvedScope['table_name']) {
                    if ($this->norm((string) $assoc['TABLE_NAME']) !== $this->norm($resolvedScope['table_name'])) {
                        continue;
                    }
                }
            } elseif ($isSiebelColumnsHeader) {
                $assoc = $this->mapSiebelColumnsRowToCanonicalAssoc($row, $siebelIndexMap);
                if (($assoc['TABLE_NAME'] ?? '') === '' || ($assoc['COLUMN_NAME'] ?? '') === '') {
                    continue;
                }
                // Apply upload-provided scope (database/schema/table) with resolved context to ensure both names present
                // Use sensible defaults when no scope is provided or resolved
                $assoc['DATABASE_NAME'] = $resolvedScope['database_name'] ?: ($assoc['DATABASE_NAME'] ?: SiebelMetadata::DEFAULT_DATABASE);
                $assoc['SCHEMA_NAME'] = $resolvedScope['schema_name'] ?: ($assoc['SCHEMA_NAME'] ?: SiebelMetadata::DEFAULT_SCHEMA);
                if ($resolvedScope['table_name']) {
                    if ($this->norm((string) $assoc['TABLE_NAME']) !== $this->norm($resolvedScope['table_name'])) {
                        continue;
                    }
                }
            } else {
                continue;
            }

            // Normalize values for validation.
            $normalized = [];
            foreach ($assoc as $k => $v) {
                if ($v === null) {
                    $normalized[$k] = null;
                    continue;
                }
                $val = is_string($v)
                    ? trim($this->normalizeUtf8String($v, false))
                    : $v;

                if (is_string($val) && in_array($k, ['COLUMN_ID', 'NUM_DISTINCT', 'NUM_NOT_NULL', 'NUM_NULLS', 'NUM_ROWS', 'DATA_LENGTH', 'DATA_PRECISION', 'DATA_SCALE', 'CHAR_LENGTH'], true)) {
                    // Accept common thousands separators from exports (e.g. "2,000").
                    $candidate = str_replace([',', ' '], '', $val);
                    if ($candidate !== '' && preg_match('/^\d+$/', $candidate)) {
                        $val = $candidate;
                    }
                }

                $normalized[$k] = ($val === '') ? null : $val;
            }

            $validator = Validator::make($normalized, $rules, [], $attributeNames);
            if ($validator->fails()) {
                $hasValidationErrors = true;
                $errorArrays = $validator->errors()->toArray();

                foreach ($errorArrays as $field => $messages) {
                    foreach ($messages as $message) {
                        if ($validationErrorCount >= self::CSV_VALIDATION_ERROR_CAP) {
                            break 3;
                        }

                        $value = $normalized[$field] ?? null;
                        if (is_string($value) && strlen($value) > 200) {
                            $value = substr($value, 0, 200) . '…';
                        }

                        $validationErrors[] = [
                            'row' => $rowNumber,
                            'field' => $field,
                            'message' => $message,
                            'value' => $value,
                        ];
                        $validationErrorCount++;
                    }
                }

                continue;
            }

            // Build core payload for hash calculation (excludes relationship fields)
            $payload = [
                'database_name' => trim((string) ($normalized['DATABASE_NAME'] ?? '')),
                'schema_name' => trim((string) ($normalized['SCHEMA_NAME'] ?? '')),
                'object_type' => strtolower(trim((string) ($normalized['OBJECT_TYPE'] ?? 'table'))),
                'table_name' => trim((string) ($normalized['TABLE_NAME'] ?? '')),
                'column_name' => trim((string) ($normalized['COLUMN_NAME'] ?? '')),
                'qualfield' => $this->toNullOrString($normalized['QUALFIELD'] ?? null),
                'anon_rule' => $this->toNullOrString($normalized['ANON_RULE'] ?? null),
                'anon_note' => $this->toNullOrString($normalized['ANON_NOTE'] ?? null),
                'column_id' => $this->toInt($normalized['COLUMN_ID'] ?? null),
                'pr_key' => $this->toNullOrString($normalized['PR_KEY'] ?? null),
                'ref_tab_name' => $this->toNullOrString($normalized['REF_TAB_NAME'] ?? null),
                'num_distinct' => $this->toInt($normalized['NUM_DISTINCT'] ?? null),
                'num_not_null' => $this->toInt($normalized['NUM_NOT_NULL'] ?? null),
                'num_nulls' => $this->toInt($normalized['NUM_NULLS'] ?? null),
                'num_rows' => $this->toInt($normalized['NUM_ROWS'] ?? null),
                'data_type' => $this->toNullOrString($normalized['DATA_TYPE'] ?? null),
                'data_length' => $this->toInt($normalized['DATA_LENGTH'] ?? null),
                'data_precision' => $this->toInt($normalized['DATA_PRECISION'] ?? null),
                'data_scale' => $this->toInt($normalized['DATA_SCALE'] ?? null),
                'nullable' => $this->toNullOrString($normalized['NULLABLE'] ?? null),
                'char_length' => $this->toInt($normalized['CHAR_LENGTH'] ?? null),
                'column_comment' => $this->toNullOrString($normalized['COLUMN_COMMENT'] ?? null),
                'sbl_user_name' => $this->toNullOrString($normalized['SBL_USER_NAME'] ?? null),
                'sbl_desc_text' => $this->toNullOrString($normalized['SBL_DESC_TEXT'] ?? null),
                'table_comment' => $this->toNullOrString($normalized['TABLE_COMMENT'] ?? null),
            ];

            // Calculate content hash before adding relationship fields
            $payload['content_hash'] = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE));

            // Add relationship fields after hash calculation
            $rawRelationships = $normalized['RELATED_COLUMNS'] ?? ($normalized['REF_TAB_NAME'] ?? null);
            if ($rawRelationships !== null && $rawRelationships !== '') {
                $rawRelationships = html_entity_decode(
                    (string) $rawRelationships,
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                );
                $rawRelationships = $this->normalizeUtf8String($rawRelationships);
                $parsedRelationships = $this->parseRelated($rawRelationships);
                $payload['related_columns'] = $parsedRelationships ? json_encode($parsedRelationships, JSON_UNESCAPED_UNICODE) : null;
            } else {
                $rawRelationships = null;
                $payload['related_columns'] = null;
            }
            $payload['related_columns_raw'] = $rawRelationships;

            // Add metadata fields
            $payload['upload_id'] = $upload->id;
            $payload['created_at'] = $now;
            $payload['updated_at'] = $now;

            // Build unique key for within-batch deduplication (last occurrence wins)
            $uniqueKey = implode('|', [
                $payload['upload_id'],
                $this->norm($payload['database_name']),
                $this->norm($payload['schema_name']),
                $this->norm($payload['table_name']),
                $this->norm($payload['column_name']),
            ]);

            $batch[$uniqueKey] = $payload;
            $hasValidRows = true;

            if (count($batch) >= 1000) {
                $count += $this->upsertStagingBatch(array_values($batch));
                $batch = [];

                // Force garbage collection for very large files
                if ($count % 10000 === 0) {
                    gc_collect_cycles();
                }
            }

            if ($progressReporter) {
                $nowAt = microtime(true);
                if (($nowAt - $lastProgressAt) >= 1.0) {
                    $lastProgressAt = $nowAt;

                    $processedBytes = null;
                    if ($totalBytes && $dataStart !== null) {
                        try {
                            $pos = ftell($stream);
                            if (is_int($pos) || is_float($pos)) {
                                $processedBytes = max(0, (int) $pos);
                            }
                        } catch (\Throwable) {
                            $processedBytes = null;
                        }
                    }

                    $payload = [
                        'status_detail' => 'Loading staging',
                        'run_phase' => 'staging',
                        'processed_rows' => $rowNumber,
                    ];
                    if ($processedBytes !== null) {
                        $payload['processed_bytes'] = $processedBytes;
                    }
                    $progressReporter($payload);
                }
            }
        }

        fclose($stream);

        if ($hasValidationErrors) {
            $errorReportPath = $this->csvErrorReportPath($upload);
            $report = [
                'upload_id' => $upload->id,
                'format' => $format,
                'header' => $header,
                'missing_header_columns' => $missingHeaderColumns,
                'unknown_header_columns' => $unknownHeaderColumns,
                'error_cap' => self::CSV_VALIDATION_ERROR_CAP,
                'capped' => $validationErrorCount >= self::CSV_VALIDATION_ERROR_CAP,
                'error_count' => $validationErrorCount,
                'errors' => $validationErrors,
                'generated_at' => now()->toIso8601String(),
            ];
            $this->writeCsvErrorReport($upload, $report);

            $warnings = array_values(array_filter((array) ($upload->warnings ?? []), static fn($warning) => is_array($warning)));
            $warnings[] = [
                'phase' => 'staging',
                'message' => 'CSV row validation reported ' . $validationErrorCount . ' error(s). Invalid rows were skipped.',
                'error_count' => $validationErrorCount,
                'error_report_path' => $errorReportPath,
                'at' => CarbonImmutable::now()->toIso8601String(),
            ];

            $upload->forceFill([
                'warnings_count' => count($warnings),
                'warnings' => $warnings,
                'status_detail' => 'CSV contained validation errors; invalid rows skipped',
            ])->save();

            $ticketTitle = 'Upload validation warnings: ' . ($upload->original_name ?: ('Upload #' . $upload->id));
            $existingTicket = ChangeTicket::query()
                ->where('upload_id', $upload->id)
                ->where('scope_type', 'upload')
                ->where('title', $ticketTitle)
                ->whereIn('status', ['open', 'in_progress'])
                ->exists();

            if (! $existingTicket) {
                ChangeTicket::create([
                    'title' => $ticketTitle,
                    'status' => 'open',
                    'priority' => 'high',
                    'severity' => 'high',
                    'scope_type' => 'upload',
                    'scope_name' => (string) ($upload->original_name ?: $upload->id),
                    'impact_summary' => 'CSV validation detected ' . $validationErrorCount . ' row-level issue(s). Import continued and invalid rows were skipped.',
                    'diff_payload' => json_encode([
                        'error_count' => $validationErrorCount,
                        'error_report_path' => $errorReportPath,
                        'format' => $format,
                        'capped' => $validationErrorCount >= self::CSV_VALIDATION_ERROR_CAP,
                    ]),
                    'upload_id' => $upload->id,
                ]);
            }
        }

        if ($batch !== []) {
            $count += $this->upsertStagingBatch(array_values($batch));
            $batch = [];
        }

        // Final cleanup
        gc_collect_cycles();

        if ($count === 0) {
            throw new RuntimeException('The uploaded CSV did not contain any data rows.');
        }

        return $count;
    }

    protected function csvErrorReportPath(AnonymousUpload $upload): ?string
    {
        if (! $upload->path) {
            return null;
        }

        return $upload->path . '.errors.json';
    }

    protected function writeCsvErrorReport(AnonymousUpload $upload, array $report): void
    {
        $path = $this->csvErrorReportPath($upload);
        if (! $path) {
            return;
        }

        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        Storage::disk($upload->file_disk)->put($path, $json);
    }

    protected function mapSiebelColumnsRowToCanonicalAssoc(array $row, array $idx): array
    {
        $get = function (string $key) use ($row, $idx) {
            if (! isset($idx[$key])) {
                return null;
            }
            $val = $row[$idx[$key]] ?? null;

            if ($val === null) {
                return null;
            }

            return trim($this->normalizeUtf8String((string) $val, false));
        };

        $parentTable = $get('PARENT TABLE') ?? '';
        $columnName = $get('NAME') ?? '';
        $dataType = strtoupper((string) ($get('PHYSICAL TYPE') ?? ''));
        $length = $get('LENGTH');
        $precision = $get('PRECISION');
        $scale = $get('SCALE');
        $nullable = strtoupper((string) ($get('NULLABLE') ?? ''));
        $fkTable = $get('FOREIGN KEY TABLE');
        $comments = $get('COMMENTS');

        return [
            'DATABASE_NAME' => null,
            'SCHEMA_NAME' => null,
            'OBJECT_TYPE' => 'TABLE',
            'TABLE_NAME' => $parentTable,
            'COLUMN_NAME' => $columnName,
            'COLUMN_ID' => null,
            'DATA_TYPE' => $dataType,
            'DATA_LENGTH' => $length,
            'DATA_PRECISION' => $precision,
            'DATA_SCALE' => $scale,
            'NULLABLE' => $nullable ?: null,
            'CHAR_LENGTH' => null,
            'TABLE_COMMENT' => null,
            'COLUMN_COMMENT' => $comments,
            'RELATED_COLUMNS' => $fkTable,
        ];
    }

    protected function mapTempRowToCanonicalAssoc(array $row, array $idx): array
    {
        $get = function (string $key) use ($row, $idx) {
            if (! isset($idx[$key])) {
                return null;
            }

            $val = $row[$idx[$key]] ?? null;

            if ($val === null) {
                return null;
            }

            return trim($this->normalizeUtf8String((string) $val, false));
        };

        $databaseName = $get('DB_INSTANCE');
        $schemaName = $get('OWNER');
        $tableName = $get('TABLE_NAME');
        $columnName = $get('COLUMN_NAME');
        $nullable = strtoupper((string) ($get('NULLABLE') ?? ''));
        $related = $get('REF_TAB_NAME');

        return [
            'DATABASE_NAME' => $databaseName,
            'SCHEMA_NAME' => $schemaName,
            'OBJECT_TYPE' => 'TABLE',
            'TABLE_NAME' => $tableName,
            'COLUMN_NAME' => $columnName,
            'QUALFIELD' => $get('QUALFIELD'),
            'COLUMN_ID' => $get('COLUMN_ID'),
            'ANON_RULE' => $get('ANON_RULE'),
            'ANON_NOTE' => $get('ANON_NOTE'),
            'PR_KEY' => $get('PR_KEY'),
            'REF_TAB_NAME' => $related,
            'NUM_DISTINCT' => $get('NUM_DISTINCT'),
            'NUM_NOT_NULL' => $get('NUM_NOT_NULL'),
            'NUM_NULLS' => $get('NUM_NULLS'),
            'NUM_ROWS' => $get('NUM_ROWS'),
            'DATA_TYPE' => $get('DATA_TYPE'),
            'DATA_LENGTH' => $get('DATA_LENGTH'),
            'DATA_PRECISION' => $get('DATA_PRECISION'),
            'DATA_SCALE' => $get('DATA_SCALE'),
            'NULLABLE' => $nullable ?: null,
            'CHAR_LENGTH' => null,
            'TABLE_COMMENT' => null,
            'COLUMN_COMMENT' => $get('COMMENTS'),
            'SBL_USER_NAME' => $get('SBL_USER_NAME'),
            'SBL_DESC_TEXT' => $get('SBL_DESC_TEXT'),
            'RELATED_COLUMNS' => $related,
        ];
    }

    protected function upsertStagingBatch(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }

        $now = now();

        foreach ($batch as &$row) {
            foreach ($row as $key => $value) {
                if (is_string($value)) {
                    $row[$key] = $this->sanitizeUtf8ForDatabase($value);
                }
            }

            $row['updated_at'] = $now;
        }

        unset($row);

        $uniqueBy = ['upload_id', 'database_name', 'schema_name', 'table_name', 'column_name'];
        $updateColumns = [
            'object_type',
            'qualfield',
            'anon_rule',
            'anon_note',
            'column_id',
            'pr_key',
            'ref_tab_name',
            'num_distinct',
            'num_not_null',
            'num_nulls',
            'num_rows',
            'data_type',
            'data_length',
            'data_precision',
            'data_scale',
            'nullable',
            'char_length',
            'column_comment',
            'sbl_user_name',
            'sbl_desc_text',
            'table_comment',
            'related_columns_raw',
            'related_columns',
            'content_hash',
            'updated_at',
        ];

        try {
            DB::table(self::STAGING_TABLE)->upsert($batch, $uniqueBy, $updateColumns);
        } catch (QueryException $exception) {
            if (! $this->isUtf8EncodingError($exception)) {
                throw $exception;
            }

            foreach ($batch as $row) {
                $retryRow = $this->sanitizeStagingRowForRetry($row);

                try {
                    DB::table(self::STAGING_TABLE)->upsert([$retryRow], $uniqueBy, $updateColumns);
                } catch (QueryException $rowException) {
                    if (! $this->isUtf8EncodingError($rowException)) {
                        throw $rowException;
                    }

                    $label = ($retryRow['database_name'] ?? '?')
                        . '.' . ($retryRow['schema_name'] ?? '?')
                        . '.' . ($retryRow['table_name'] ?? '?')
                        . '.' . ($retryRow['column_name'] ?? '?');

                    throw new RuntimeException('UTF-8 sanitization failed for staging row: ' . $label, 0, $rowException);
                }
            }
        }

        return count($batch);
    }

    protected function sanitizeStagingRowForRetry(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $row[$key] = $this->sanitizeUtf8ForDatabase($value);
            }
        }

        return $row;
    }

    protected function isUtf8EncodingError(QueryException $exception): bool
    {
        $message = (string) $exception->getMessage();

        return str_contains($message, 'SQLSTATE[22021]')
            || str_contains($message, 'invalid byte sequence for encoding "UTF8"');
    }

    protected function chunkedUpsert(string $table, array $rows, array $uniqueBy, array $updateColumns, int $chunkSize = self::METADATA_UPSERT_CHUNK_SIZE): void
    {
        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            // Deduplicate chunk by uniqueBy keys to prevent ON CONFLICT errors
            // Last occurrence wins to match CSV semantics
            $deduplicated = [];
            foreach ($chunk as $row) {
                $key = implode('|', array_map(fn($col) => $this->norm((string) ($row[$col] ?? '')), $uniqueBy));
                $deduplicated[$key] = $row;
            }

            DB::table($table)->upsert(array_values($deduplicated), $uniqueBy, $updateColumns);
        }
    }

    protected function databaseCacheKey(string $databaseName): string
    {
        return $this->norm($databaseName);
    }

    protected function schemaCacheKey(int $databaseId, string $schemaName): string
    {
        return $databaseId . '|' . $this->norm($schemaName);
    }

    protected function tableCacheKey(int $schemaId, string $tableName): string
    {
        return $schemaId . '|' . $this->norm($tableName);
    }

    protected function dataTypeCacheKey(?string $dataType): ?string
    {
        if ($dataType === null || $dataType === '') {
            return null;
        }

        return $this->norm($dataType);
    }

    protected function synchronizeSiebelMetadataFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $databaseMap = $this->refreshDatabasesFromStaging($uploadId, $runAt);
        $schemaMap = $this->refreshSchemasFromStaging($uploadId, $runAt, $databaseMap);
        $tableMap = $this->refreshTablesFromStaging($uploadId, $runAt, $schemaMap, $databaseMap);
        $dataTypeMap = $this->refreshDataTypesFromStaging($uploadId, $runAt);

        return [
            'databases' => $databaseMap,
            'schemas' => $schemaMap,
            'tables' => $tableMap,
            'data_types' => $dataTypeMap,
        ];
    }

    protected function refreshDatabasesFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $names = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->distinct()
            ->pluck('database_name')
            ->filter(fn($name) => $name !== null && trim($name) !== '')
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $existing = DB::table(self::DATABASES_TABLE)
            ->whereIn('database_name', $names->all())
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->databaseCacheKey($record->database_name)] = $record;
        }

        $rows = [];
        foreach ($names as $databaseName) {
            $databaseName = trim((string) $databaseName);
            if ($databaseName === '') {
                continue;
            }

            $key = $this->databaseCacheKey($databaseName);
            $existingRecord = $existingMap[$key] ?? null;
            $hash = $this->hashFor([
                'database_name' => $databaseName,
            ]);

            if ($existingRecord) {
                $diff = [];

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rows[] = [
                    'database_name' => $databaseName,
                    'description' => $existingRecord->description,
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rows[] = [
                    'database_name' => $databaseName,
                    'description' => null,
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::DATABASES_TABLE,
            $rows,
            ['database_name'],
            ['description', 'content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $map = [];
        $records = DB::table(self::DATABASES_TABLE)
            ->whereIn('database_name', $names->all())
            ->get();

        foreach ($records as $record) {
            $map[$this->databaseCacheKey($record->database_name)] = [
                'id' => (int) $record->id,
                'name' => $record->database_name,
            ];
        }

        return $map;
    }

    protected function refreshSchemasFromStaging(int $uploadId, CarbonImmutable $runAt, array $databaseMap): array
    {
        if ($databaseMap === []) {
            return [];
        }

        $rows = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->select('database_name', 'schema_name')
            ->distinct()
            ->get();

        $schemas = [];
        $schemaNames = [];
        $databaseIds = [];

        foreach ($rows as $row) {
            $databaseName = trim((string) $row->database_name);
            if ($databaseName === '') {
                continue;
            }

            $databaseKey = $this->databaseCacheKey($databaseName);
            $databaseEntry = $databaseMap[$databaseKey] ?? null;
            if (! $databaseEntry) {
                continue;
            }

            $schemaName = trim((string) $row->schema_name);
            if ($schemaName === '') {
                continue;
            }

            $schemaKey = $this->schemaCacheKey($databaseEntry['id'], $schemaName);
            $schemas[$schemaKey] = [
                'database_id' => $databaseEntry['id'],
                'schema_name' => $schemaName,
            ];

            $schemaNames[$schemaName] = true;
            $databaseIds[$databaseEntry['id']] = true;
        }

        if ($schemas === []) {
            return [];
        }

        $existing = DB::table(self::SCHEMAS_TABLE)
            ->whereIn('database_id', array_keys($databaseIds))
            ->whereIn('schema_name', array_keys($schemaNames))
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->schemaCacheKey((int) $record->database_id, $record->schema_name)] = $record;
        }

        $rowsForUpsert = [];
        foreach ($schemas as $schemaKey => $schema) {
            $existingRecord = $existingMap[$schemaKey] ?? null;
            $hash = $this->hashFor([
                'schema_name' => $schema['schema_name'],
                'database_id' => $schema['database_id'],
            ]);

            if ($existingRecord) {
                $diff = [];

                if ((int) $existingRecord->database_id !== $schema['database_id']) {
                    $diff['database_id'] = [
                        'old' => (int) $existingRecord->database_id,
                        'new' => $schema['database_id'],
                    ];
                }

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rowsForUpsert[] = [
                    'database_id' => $schema['database_id'],
                    'schema_name' => $schema['schema_name'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rowsForUpsert[] = [
                    'database_id' => $schema['database_id'],
                    'schema_name' => $schema['schema_name'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::SCHEMAS_TABLE,
            $rowsForUpsert,
            ['database_id', 'schema_name'],
            ['content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::SCHEMAS_TABLE)
            ->whereIn('database_id', array_keys($databaseIds))
            ->whereIn('schema_name', array_keys($schemaNames))
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->schemaCacheKey((int) $record->database_id, $record->schema_name)] = [
                'id' => (int) $record->id,
                'database_id' => (int) $record->database_id,
                'schema_name' => $record->schema_name,
            ];
        }

        return $map;
    }

    protected function refreshTablesFromStaging(int $uploadId, CarbonImmutable $runAt, array $schemaMap, array $databaseMap): array
    {
        if ($schemaMap === [] || $databaseMap === []) {
            return [];
        }

        $rows = DB::table(self::STAGING_TABLE . ' as s')
            ->where('s.upload_id', $uploadId)
            ->select(
                's.database_name',
                's.schema_name',
                's.table_name',
                DB::raw('max(s.object_type) as object_type'),
                DB::raw('max(s.table_comment) as table_comment')
            )
            ->groupBy('s.database_name', 's.schema_name', 's.table_name')
            ->get();

        $tables = [];
        $schemaIds = [];
        $tableNames = [];

        foreach ($rows as $row) {
            $databaseName = trim((string) $row->database_name);
            $schemaName = trim((string) $row->schema_name);
            $tableName = trim((string) $row->table_name);

            if ($databaseName === '' || $schemaName === '' || $tableName === '') {
                continue;
            }

            $databaseKey = $this->databaseCacheKey($databaseName);
            $databaseEntry = $databaseMap[$databaseKey] ?? null;
            if (! $databaseEntry) {
                continue;
            }

            $schemaKey = $this->schemaCacheKey($databaseEntry['id'], $schemaName);
            $schemaEntry = $schemaMap[$schemaKey] ?? null;

            if (! $schemaEntry) {
                continue;
            }

            $schemaId = $schemaEntry['id'];

            $tableKey = $this->tableCacheKey($schemaId, $tableName);

            $tables[$tableKey] = [
                'schema_id' => $schemaId,
                'database_id' => $schemaEntry['database_id'],
                'table_name' => $tableName,
                'object_type' => $row->object_type ? strtolower($row->object_type) : 'table',
                'table_comment' => $row->table_comment,
            ];

            $schemaIds[$schemaId] = true;
            $tableNames[$tableName] = true;
        }

        if ($tables === []) {
            return [];
        }

        $existing = DB::table(self::TABLES_TABLE)
            ->whereIn('schema_id', array_keys($schemaIds))
            ->whereIn('table_name', array_keys($tableNames))
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $existingMap[$this->tableCacheKey((int) $record->schema_id, $record->table_name)] = $record;
        }

        $rowsForUpsert = [];
        foreach ($tables as $tableKey => $table) {
            $existingRecord = $existingMap[$tableKey] ?? null;
            $hash = $this->hashFor([
                'table_name' => $table['table_name'],
                'schema_id' => $table['schema_id'],
                'object_type' => $table['object_type'],
                'table_comment' => $table['table_comment'],
            ]);

            if ($existingRecord) {
                $diff = [];

                if ($existingRecord->object_type !== $table['object_type']) {
                    $diff['object_type'] = [
                        'old' => $existingRecord->object_type,
                        'new' => $table['object_type'],
                    ];
                }

                if ($existingRecord->table_comment !== $table['table_comment']) {
                    $diff['table_comment'] = [
                        'old' => $existingRecord->table_comment,
                        'new' => $table['table_comment'],
                    ];
                }

                if ($existingRecord->content_hash !== $hash) {
                    $diff['content_hash'] = [
                        'old' => $existingRecord->content_hash,
                        'new' => $hash,
                    ];
                }

                $rowsForUpsert[] = [
                    'schema_id' => $table['schema_id'],
                    'table_name' => $table['table_name'],
                    'object_type' => $table['object_type'],
                    'table_comment' => $table['table_comment'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => $diff ? $runAt : $existingRecord->changed_at,
                    'changed_fields' => $diff ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existingRecord->changed_fields,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rowsForUpsert[] = [
                    'schema_id' => $table['schema_id'],
                    'table_name' => $table['table_name'],
                    'object_type' => $table['object_type'],
                    'table_comment' => $table['table_comment'],
                    'content_hash' => $hash,
                    'last_synced_at' => $runAt,
                    'changed_at' => null,
                    'changed_fields' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::TABLES_TABLE,
            $rowsForUpsert,
            ['schema_id', 'table_name'],
            ['object_type', 'table_comment', 'content_hash', 'last_synced_at', 'changed_at', 'changed_fields', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::TABLES_TABLE)
            ->whereIn('schema_id', array_keys($schemaIds))
            ->whereIn('table_name', array_keys($tableNames))
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->tableCacheKey((int) $record->schema_id, $record->table_name)] = [
                'id' => (int) $record->id,
                'schema_id' => (int) $record->schema_id,
                'table_name' => $record->table_name,
            ];
        }

        return $map;
    }

    protected function refreshDataTypesFromStaging(int $uploadId, CarbonImmutable $runAt): array
    {
        $names = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->whereNotNull('data_type')
            ->distinct()
            ->pluck('data_type')
            ->filter(fn($name) => $name !== null && trim($name) !== '')
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $existing = DB::table(self::DATA_TYPES_TABLE)
            ->whereIn('data_type_name', $names->all())
            ->get();

        $existingMap = [];
        foreach ($existing as $record) {
            $key = $this->dataTypeCacheKey($record->data_type_name);
            if ($key !== null) {
                $existingMap[$key] = $record;
            }
        }

        $rows = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $key = $this->dataTypeCacheKey($name);
            if ($key === null) {
                continue;
            }

            $existingRecord = $existingMap[$key] ?? null;

            if ($existingRecord) {
                $rows[] = [
                    'data_type_name' => $name,
                    'description' => $existingRecord->description,
                    'deleted_at' => null,
                    'created_at' => $existingRecord->created_at,
                    'updated_at' => $runAt,
                ];
            } else {
                $rows[] = [
                    'data_type_name' => $name,
                    'description' => null,
                    'deleted_at' => null,
                    'created_at' => $runAt,
                    'updated_at' => $runAt,
                ];
            }
        }

        $this->chunkedUpsert(
            self::DATA_TYPES_TABLE,
            $rows,
            ['data_type_name'],
            ['description', 'deleted_at', 'updated_at']
        );

        $records = DB::table(self::DATA_TYPES_TABLE)
            ->whereIn('data_type_name', $names->all())
            ->get();

        $map = [];
        foreach ($records as $record) {
            $key = $this->dataTypeCacheKey($record->data_type_name);
            if ($key !== null) {
                $map[$key] = [
                    'id' => (int) $record->id,
                    'name' => $record->data_type_name,
                ];
            }
        }

        return $map;
    }

    protected function loadExistingColumnsForChunk(array $tableColumns): array
    {
        if ($tableColumns === []) {
            return [];
        }

        $records = DB::table(self::COLUMNS_TABLE)
            ->where(function ($query) use ($tableColumns) {
                foreach ($tableColumns as $tableId => $columns) {
                    $normalizedColumns = array_values(array_unique(array_filter(array_map(
                        fn($name) => is_string($name) ? trim($name) : null,
                        $columns
                    ))));

                    if ($normalizedColumns === []) {
                        continue;
                    }

                    $query->orWhere(function ($nested) use ($tableId, $normalizedColumns) {
                        $nested
                            ->where('table_id', (int) $tableId)
                            ->whereIn('column_name', $normalizedColumns);
                    });
                }
            })
            ->get();

        $map = [];
        foreach ($records as $record) {
            $map[$this->columnKey((int) $record->table_id, $record->column_name)] = $record;
        }

        return $map;
    }

    protected function syncColumnsFromStaging(
        AnonymousUpload $upload,
        CarbonImmutable $runAt,
        ?int $totalBytes,
        int $stagingCount,
        array $databaseMap,
        array $schemaMap,
        array $tableMap,
        array $dataTypeMap,
        ?callable $progressReporter = null
    ): array {
        $uploadId = $upload->id;
        $chunkSize = 1500;
        $processedRows = 0;
        $processedBytes = $totalBytes ? 0 : null;
        $totals = [
            'inserted' => 0,
            'updated' => 0,
        ];

        $touchedTableIdentities = [];

        // Use temporary tables to track processed column identities instead of keeping them in memory
        // This prevents memory exhaustion with very large imports
        $tempColumnIdentitiesTable = 'temp_column_identities_' . $uploadId;
        DB::statement("CREATE TEMPORARY TABLE {$tempColumnIdentitiesTable} (
            table_id INTEGER NOT NULL,
            column_name VARCHAR(512) NOT NULL,
            PRIMARY KEY (table_id, column_name)
        )");

        $columnIdentitiesBatch = [];
        $columnIdentitiesBatchSize = 5000;

        // Use a temporary table to track touched column IDs instead of keeping them in memory
        // This prevents memory exhaustion with very large imports
        $tempTableName = 'temp_touched_columns_' . $uploadId;
        DB::statement("CREATE TEMPORARY TABLE {$tempTableName} (column_id INTEGER PRIMARY KEY)");

        $touchedColumnIdsBatch = [];
        $touchedColumnIdsBatchSize = 5000;
        $lastProgressAt = microtime(true);

        DB::table(self::STAGING_TABLE . ' as s')
            ->where('s.upload_id', $uploadId)
            ->orderBy('s.id')
            ->chunkById($chunkSize, function ($rows) use (
                &$processedRows,
                &$processedBytes,
                &$totals,
                &$touchedTableIdentities,
                &$columnIdentitiesBatch,
                $columnIdentitiesBatchSize,
                $tempColumnIdentitiesTable,
                &$touchedColumnIdsBatch,
                $touchedColumnIdsBatchSize,
                $tempTableName,
                $stagingCount,
                $totalBytes,
                $runAt,
                $databaseMap,
                $schemaMap,
                $tableMap,
                $dataTypeMap,
                $progressReporter,
                &$lastProgressAt
            ) {
                $tableColumns = [];

                foreach ($rows as $row) {
                    $databaseKey = $this->databaseCacheKey($row->database_name);
                    $databaseEntry = $databaseMap[$databaseKey] ?? null;
                    if (! $databaseEntry) {
                        continue;
                    }

                    $schemaKey = $this->schemaCacheKey($databaseEntry['id'], (string) $row->schema_name);
                    $schemaEntry = $schemaMap[$schemaKey] ?? null;
                    if (! $schemaEntry) {
                        continue;
                    }

                    $tableName = trim((string) $row->table_name);
                    if ($tableName === '') {
                        continue;
                    }

                    $tableKey = $this->tableCacheKey($schemaEntry['id'], $tableName);
                    $tableEntry = $tableMap[$tableKey] ?? null;
                    if (! $tableEntry) {
                        continue;
                    }

                    $columnName = trim((string) $row->column_name);
                    if ($columnName === '') {
                        continue;
                    }

                    $tableColumns[$tableEntry['id']][] = $columnName;
                }

                $existingColumns = $this->loadExistingColumnsForChunk($tableColumns);

                $rowsForUpsert = [];
                $logCreated = [];
                $logUpdated = [];
                $logRestored = [];

                foreach ($rows as $row) {
                    $databaseKey = $this->databaseCacheKey($row->database_name);
                    $databaseEntry = $databaseMap[$databaseKey] ?? null;
                    if (! $databaseEntry) {
                        continue;
                    }

                    $schemaKey = $this->schemaCacheKey($databaseEntry['id'], (string) $row->schema_name);
                    $schemaEntry = $schemaMap[$schemaKey] ?? null;
                    if (! $schemaEntry) {
                        continue;
                    }

                    $tableName = trim((string) $row->table_name);
                    if ($tableName === '') {
                        continue;
                    }

                    $tableKey = $this->tableCacheKey($schemaEntry['id'], $tableName);
                    $tableEntry = $tableMap[$tableKey] ?? null;
                    if (! $tableEntry) {
                        continue;
                    }

                    $columnName = trim((string) $row->column_name);
                    if ($columnName === '') {
                        continue;
                    }

                    $columnKey = $this->columnKey($tableEntry['id'], $columnName);
                    $existing = $existingColumns[$columnKey] ?? null;

                    if ($existing) {
                        // Batch the touched column IDs for temporary table insert
                        $touchedColumnIdsBatch[(int) $existing->id] = true;
                        if (count($touchedColumnIdsBatch) >= $touchedColumnIdsBatchSize) {
                            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
                            $touchedColumnIdsBatch = [];
                        }
                    }

                    $dataTypeKey = $this->dataTypeCacheKey($row->data_type);
                    $dataTypeEntry = $dataTypeKey ? ($dataTypeMap[$dataTypeKey] ?? null) : null;
                    $dataTypeId = $dataTypeEntry['id'] ?? null;

                    $nullableFlag = $this->toNullableFlag($row->nullable);

                    $payload = [
                        'table_id' => $tableEntry['id'],
                        'column_name' => $columnName,
                        'qualfield' => $row->qualfield,
                        'column_id' => $row->column_id,
                        'pr_key' => $row->pr_key,
                        'ref_tab_name' => $row->ref_tab_name,
                        'num_distinct' => $row->num_distinct,
                        'num_not_null' => $row->num_not_null,
                        'num_nulls' => $row->num_nulls,
                        'num_rows' => $row->num_rows,
                        'data_type_id' => $dataTypeId,
                        'data_length' => $row->data_length,
                        'data_precision' => $row->data_precision,
                        'data_scale' => $row->data_scale,
                        'nullable' => $nullableFlag,
                        'char_length' => $row->char_length,
                        'column_comment' => $row->column_comment,
                        'sbl_user_name' => $row->sbl_user_name,
                        'sbl_desc_text' => $row->sbl_desc_text,
                        'table_comment' => $row->table_comment,
                        'related_columns_raw' => $row->related_columns_raw,
                        'related_columns' => $row->related_columns,
                        'content_hash' => $row->content_hash,
                        'last_synced_at' => $runAt,
                    ];

                    if ($existing) {
                        $payload = $this->preserveExistingValuesForMissingImportData($existing, $payload);
                    }

                    $rowForUpsert = array_merge($payload, [
                        'deleted_at' => null,
                        'updated_at' => $runAt,
                    ]);

                    $fieldsForDiff = array_keys(Arr::except($payload, ['table_id', 'column_name', 'last_synced_at']));

                    if ($existing) {
                        $diff = $this->diffValues($existing, $payload, $fieldsForDiff);
                        $wasDeleted = $existing->deleted_at !== null;
                        $hasChanges = $diff !== [] || $wasDeleted;

                        $rowForUpsert['changed_at'] = $hasChanges ? $runAt : $existing->changed_at;
                        $rowForUpsert['changed_fields'] = $hasChanges ? json_encode($diff, JSON_UNESCAPED_UNICODE) : $existing->changed_fields;
                        $rowForUpsert['last_synced_at'] = $runAt;
                        $rowForUpsert['created_at'] = $existing->created_at;
                        $rowForUpsert['anonymization_requirement_reviewed'] = $existing->anonymization_requirement_reviewed;

                        if ($diff !== []) {
                            $rowForUpsert['anonymization_requirement_reviewed'] = false;
                        }

                        if ($hasChanges) {
                            if ($wasDeleted) {
                                $logRestored[] = [
                                    'id' => (int) $existing->id,
                                    'diff' => [
                                        'deleted_at' => [
                                            'old' => $existing->deleted_at,
                                            'new' => null,
                                        ],
                                    ],
                                ];
                            }

                            if ($diff !== []) {
                                $logUpdated[] = [
                                    'id' => (int) $existing->id,
                                    'diff' => $diff,
                                ];
                            }

                            ++$totals['updated'];
                        }
                    } else {
                        $rowForUpsert['created_at'] = $runAt;
                        $rowForUpsert['changed_at'] = null;
                        $rowForUpsert['changed_fields'] = null;
                        $rowForUpsert['anonymization_requirement_reviewed'] = null;

                        $logCreated[] = [
                            'table_id' => $tableEntry['id'],
                            'column_name' => $columnName,
                        ];

                        ++$totals['inserted'];
                    }

                    $rowsForUpsert[] = $rowForUpsert;

                    $tableIdentityKey = $this->tableIdentityKey($databaseEntry['id'], $row->schema_name, $tableName);
                    $touchedTableIdentities[$tableIdentityKey] = [
                        'database_id' => $databaseEntry['id'],
                        'schema_name' => $row->schema_name,
                        'table_name' => $tableName,
                    ];

                    $columnIdentityKey = $tableEntry['id'] . '|' . $this->norm($columnName);

                    // Batch the processed column identities for temporary table insert
                    $columnIdentitiesBatch[$columnIdentityKey] = [
                        'table_id' => $tableEntry['id'],
                        'column_name' => $this->norm($columnName),
                    ];
                    if (count($columnIdentitiesBatch) >= $columnIdentitiesBatchSize) {
                        $this->flushColumnIdentities($tempColumnIdentitiesTable, $columnIdentitiesBatch);
                        $columnIdentitiesBatch = [];
                    }
                }

                if ($rowsForUpsert !== []) {
                    DB::table(self::COLUMNS_TABLE)->upsert(
                        $rowsForUpsert,
                        ['table_id', 'column_name'],
                        [
                            'qualfield',
                            'column_id',
                            'pr_key',
                            'ref_tab_name',
                            'num_distinct',
                            'num_not_null',
                            'num_nulls',
                            'num_rows',
                            'data_type_id',
                            'data_length',
                            'data_precision',
                            'data_scale',
                            'nullable',
                            'char_length',
                            'column_comment',
                            'sbl_user_name',
                            'sbl_desc_text',
                            'table_comment',
                            'related_columns_raw',
                            'related_columns',
                            'content_hash',
                            'last_synced_at',
                            'changed_at',
                            'changed_fields',
                            'anonymization_requirement_reviewed',
                            'deleted_at',
                            'updated_at',
                        ]
                    );
                }

                if ($logCreated !== []) {
                    $insertedRecords = DB::table(self::COLUMNS_TABLE)
                        ->where(function ($query) use ($logCreated) {
                            $byTable = [];

                            foreach ($logCreated as $entry) {
                                $tableId = (int) ($entry['table_id'] ?? 0);
                                $columnName = isset($entry['column_name']) ? trim((string) $entry['column_name']) : '';

                                if ($tableId <= 0 || $columnName === '') {
                                    continue;
                                }

                                $byTable[$tableId][$columnName] = true;
                            }

                            foreach ($byTable as $tableId => $columns) {
                                $query->orWhere(function ($nested) use ($tableId, $columns) {
                                    $nested
                                        ->where('table_id', $tableId)
                                        ->whereIn('column_name', array_keys($columns));
                                });
                            }
                        })
                        ->get();

                    $insertedMap = [];
                    foreach ($insertedRecords as $record) {
                        $insertedMap[$this->columnKey((int) $record->table_id, $record->column_name)] = (int) $record->id;
                    }

                    foreach ($logCreated as $entry) {
                        $key = $this->columnKey($entry['table_id'], $entry['column_name']);
                        if (isset($insertedMap[$key])) {
                            AnonymizerActivityLogger::logColumnEvent(
                                $insertedMap[$key],
                                'created',
                                [],
                                [
                                    'upload_id' => $this->uploadId,
                                ]
                            );
                        }
                    }

                    foreach ($insertedMap as $columnId) {
                        // Batch the touched column IDs for temporary table insert
                        $touchedColumnIdsBatch[$columnId] = true;
                        if (count($touchedColumnIdsBatch) >= $touchedColumnIdsBatchSize) {
                            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
                            $touchedColumnIdsBatch = [];
                        }
                    }
                }

                foreach ($logRestored as $event) {
                    AnonymizerActivityLogger::logColumnEvent(
                        $event['id'],
                        'restored',
                        $event['diff'],
                        [
                            'upload_id' => $this->uploadId,
                        ]
                    );
                }

                foreach ($logUpdated as $event) {
                    AnonymizerActivityLogger::logColumnEvent(
                        $event['id'],
                        'updated',
                        $event['diff'],
                        [
                            'upload_id' => $this->uploadId,
                        ]
                    );
                }

                $processedRows += count($rows);

                // Proactive garbage collection every 10 chunks to prevent memory accumulation
                if ($processedRows % 15000 === 0) {
                    gc_collect_cycles();
                }

                if ($totalBytes) {
                    $processedBytes = (int) min($totalBytes, floor($totalBytes * ($processedRows / max($stagingCount, 1))));
                } else {
                    $processedBytes = $processedRows;
                }

                if ($progressReporter) {
                    $shouldReport = $processedRows <= 5 || ($processedRows % 5000 === 0);
                    $now = microtime(true);
                    if (! $shouldReport && ($now - $lastProgressAt) >= 2.0) {
                        $shouldReport = true;
                    }

                    if ($shouldReport) {
                        $lastProgressAt = $now;
                        $progressReporter([
                            'processed_rows' => $processedRows,
                            'processed_bytes' => $processedBytes,
                            'inserted' => $totals['inserted'],
                            'updated' => $totals['updated'],
                            'status_detail' => sprintf('Upserting columns (%d/%d)', $processedRows, $stagingCount),
                        ]);
                    }
                }
            });

        // Flush any remaining batched touched column IDs
        if (! empty($touchedColumnIdsBatch)) {
            $this->flushTouchedColumnIds($tempTableName, $touchedColumnIdsBatch);
        }

        // Flush any remaining batched column identities
        if (! empty($columnIdentitiesBatch)) {
            $this->flushColumnIdentities($tempColumnIdentitiesTable, $columnIdentitiesBatch);
        }

        // Return temp table names instead of loading all data into memory
        // The consuming methods will query these tables in chunks
        return [
            'totals' => $totals,
            'touchedTableIdentities' => $touchedTableIdentities,
            'processedColumnIdentitiesTempTable' => $tempColumnIdentitiesTable,
            'processedRows' => $stagingCount,
            'processedBytes' => $totalBytes ?? ($processedBytes ?? 0),
            'touchedColumnIdsTempTable' => $tempTableName,
        ];
    }

    protected function preserveExistingValuesForMissingImportData(object $existing, array $payload): array
    {
        $preservedAnyField = false;

        foreach ($payload as $field => $value) {
            if ($value !== null) {
                continue;
            }

            if (in_array($field, ['table_id', 'column_name', 'last_synced_at'], true)) {
                continue;
            }

            if (property_exists($existing, $field)) {
                $payload[$field] = $existing->{$field};
                $preservedAnyField = true;
            }
        }

        if ($preservedAnyField && property_exists($existing, 'content_hash')) {
            $payload['content_hash'] = $existing->content_hash;
        }

        return $payload;
    }

    /**
     * Flush a batch of touched column IDs to the temporary table.
     * This prevents memory exhaustion by keeping IDs in the database instead of in memory.
     */
    protected function flushTouchedColumnIds(string $tempTableName, array &$batch): void
    {
        if ($batch === []) {
            return;
        }

        $rows = array_map(fn($id) => ['column_id' => $id], array_keys($batch));
        DB::table($tempTableName)->insertOrIgnore($rows);
    }

    /**
     * Flush a batch of processed column identities to the temporary table.
     * This prevents memory exhaustion by keeping identities in the database instead of in memory.
     */
    protected function flushColumnIdentities(string $tempTableName, array &$batch): void
    {
        if ($batch === []) {
            return;
        }

        DB::table($tempTableName)->insertOrIgnore(array_values($batch));
    }

    protected function synchronizeAnonymizationRulesFromStaging(AnonymousUpload $upload, CarbonImmutable $runAt, ?callable $progressReporter = null): array
    {
        $override = (bool) ($upload->override_anonymization_rules ?? false);

        if (! $override) {
            return [
                'changed_columns' => 0,
            ];
        }

        $methodIdsByName = AnonymizationMethods::query()
            ->select('id', 'name')
            ->get()
            ->mapWithKeys(fn(AnonymizationMethods $method) => [$this->norm((string) $method->name) => (int) $method->id])
            ->all();

        $baseQuery = DB::table(self::STAGING_TABLE . ' as s')
            ->join(self::DATABASES_TABLE . ' as d', DB::raw('UPPER(TRIM(d.database_name))'), '=', DB::raw('UPPER(TRIM(s.database_name))'))
            ->join(self::SCHEMAS_TABLE . ' as sc', function ($join) {
                $join->on('sc.database_id', '=', 'd.id')
                    ->whereRaw('UPPER(TRIM(sc.schema_name)) = UPPER(TRIM(s.schema_name))');
            })
            ->join(self::TABLES_TABLE . ' as t', function ($join) {
                $join->on('t.schema_id', '=', 'sc.id')
                    ->whereRaw('UPPER(TRIM(t.table_name)) = UPPER(TRIM(s.table_name))');
            })
            ->join(self::COLUMNS_TABLE . ' as c', function ($join) {
                $join->on('c.table_id', '=', 't.id')
                    ->whereRaw('UPPER(TRIM(c.column_name)) = UPPER(TRIM(s.column_name))');
            })
            ->where('s.upload_id', $upload->id);

        $totalRuleRows = (int) (clone $baseQuery)->count();
        $processedRuleRows = 0;
        $metrics = [
            'rows_with_rule_input' => 0,
            'required_updates' => 0,
            'method_mapping_updates' => 0,
            'method_links_inserted' => 0,
            'method_links_deleted' => 0,
            'unknown_method_names' => 0,
            'rows_noop' => 0,
            'rows_invalid_column' => 0,
        ];
        $changedColumns = [];
        $unknownMethods = [];

        if ($progressReporter) {
            $initialStatus = $totalRuleRows > 0
                ? sprintf('Applying anonymization rules (0/%d)', $totalRuleRows)
                : 'Applying anonymization rules (no matching rows)';

            $progressReporter([
                'status_detail' => $initialStatus,
                'run_phase' => 'applying_anonymization_rules',
                'processed_rows' => 0,
                'checkpoint' => [
                    'anonymization_rules_total_rows' => $totalRuleRows,
                    'anonymization_rules_processed_rows' => 0,
                    'anonymization_rules_changed_columns' => 0,
                    'anonymization_rules_rows_with_rule_input' => 0,
                    'anonymization_rules_required_updates' => 0,
                    'anonymization_rules_method_mapping_updates' => 0,
                    'anonymization_rules_method_links_inserted' => 0,
                    'anonymization_rules_method_links_deleted' => 0,
                    'anonymization_rules_unknown_method_names' => 0,
                    'anonymization_rules_rows_noop' => 0,
                    'anonymization_rules_rows_invalid_column' => 0,
                ],
                'progress_updated_at' => CarbonImmutable::now(),
            ]);
        }

        $query = $baseQuery
            ->select('s.id as staging_id', 'c.id as column_id', 's.database_name', 's.schema_name', 's.table_name', 's.column_name', 's.anon_rule', 's.anon_note')
            ->orderBy('s.id');

        $query->chunkById(1000, function ($rows) use ($override, $runAt, $methodIdsByName, &$processedRuleRows, $totalRuleRows, $progressReporter, &$metrics, &$changedColumns, &$unknownMethods): void {
            $effectiveRowsByColumn = [];
            foreach ($rows as $row) {
                $columnId = (int) $row->column_id;
                if ($columnId <= 0) {
                    $metrics['rows_invalid_column']++;
                    continue;
                }

                // Last row wins for duplicate staged entries of the same column within this chunk.
                $effectiveRowsByColumn[$columnId] = $row;
            }

            $columnIds = array_keys($effectiveRowsByColumn);
            $effectiveRows = array_values($effectiveRowsByColumn);

            $processedRuleRows += $rows->count();

            if ($progressReporter) {
                $changedColumnCount = count($changedColumns);
                $status = $totalRuleRows > 0
                    ? sprintf(
                        'Applying anonymization rules (%d/%d) · cols:%d req:%d map:%d links:+%d/-%d unknown:%d noop:%d',
                        min($processedRuleRows, $totalRuleRows),
                        $totalRuleRows,
                        $changedColumnCount,
                        $metrics['required_updates'],
                        $metrics['method_mapping_updates'],
                        $metrics['method_links_inserted'],
                        $metrics['method_links_deleted'],
                        $metrics['unknown_method_names'],
                        $metrics['rows_noop']
                    )
                    : sprintf(
                        'Applying anonymization rules (%d) · cols:%d req:%d map:%d links:+%d/-%d unknown:%d noop:%d',
                        $processedRuleRows,
                        $changedColumnCount,
                        $metrics['required_updates'],
                        $metrics['method_mapping_updates'],
                        $metrics['method_links_inserted'],
                        $metrics['method_links_deleted'],
                        $metrics['unknown_method_names'],
                        $metrics['rows_noop']
                    );

                $progressReporter([
                    'status_detail' => $status,
                    'run_phase' => 'applying_anonymization_rules',
                    'processed_rows' => $processedRuleRows,
                    'checkpoint' => [
                        'anonymization_rules_total_rows' => $totalRuleRows,
                        'anonymization_rules_processed_rows' => $processedRuleRows,
                        'anonymization_rules_changed_columns' => $changedColumnCount,
                        'anonymization_rules_rows_with_rule_input' => $metrics['rows_with_rule_input'],
                        'anonymization_rules_required_updates' => $metrics['required_updates'],
                        'anonymization_rules_method_mapping_updates' => $metrics['method_mapping_updates'],
                        'anonymization_rules_method_links_inserted' => $metrics['method_links_inserted'],
                        'anonymization_rules_method_links_deleted' => $metrics['method_links_deleted'],
                        'anonymization_rules_unknown_method_names' => $metrics['unknown_method_names'],
                        'anonymization_rules_rows_noop' => $metrics['rows_noop'],
                        'anonymization_rules_rows_invalid_column' => $metrics['rows_invalid_column'],
                    ],
                    'progress_updated_at' => CarbonImmutable::now(),
                ]);
            }

            if ($columnIds === []) {
                return;
            }

            $existingColumns = DB::table(self::COLUMNS_TABLE)
                ->whereIn('id', $columnIds)
                ->select('id', 'anonymization_required', 'changed_fields')
                ->get();

            $existingRequiredByColumn = [];
            $existingChangedFieldsByColumn = [];
            foreach ($existingColumns as $existingColumn) {
                $existingRequiredByColumn[(int) $existingColumn->id] = $existingColumn->anonymization_required;
                $existingChangedFieldsByColumn[(int) $existingColumn->id] = $this->normalizeChangedFieldsPayload($existingColumn->changed_fields);
            }

            $existingMethodRows = DB::table('anonymization_method_column')
                ->whereIn('column_id', $columnIds)
                ->select('column_id', 'method_id')
                ->orderBy('method_id')
                ->get();

            $existingMethodIdsByColumn = [];
            foreach ($existingMethodRows as $methodRow) {
                $existingMethodIdsByColumn[(int) $methodRow->column_id][] = (int) $methodRow->method_id;
            }

            foreach ($effectiveRows as $row) {
                $columnId = (int) $row->column_id;
                $rowChanged = false;
                $requiredUpdated = false;

                $anonRule = $this->toNullOrString($row->anon_rule);
                $anonNote = $this->toNullOrString($row->anon_note);

                if ($anonRule !== null || $anonNote !== null) {
                    $metrics['rows_with_rule_input']++;
                }

                if ($override || $anonRule !== null) {
                    $required = $this->parseAnonRuleRequiredFlag($anonRule);

                    if ($override || $required !== null) {
                        $currentRequired = $existingRequiredByColumn[$columnId] ?? null;

                        if ($this->valuesDiffer($currentRequired, $required)) {
                            $changedFields = $existingChangedFieldsByColumn[$columnId] ?? [];
                            $changedFields['anonymization_required'] = [
                                'old' => $currentRequired,
                                'new' => $required,
                            ];

                            DB::table(self::COLUMNS_TABLE)
                                ->where('id', $columnId)
                                ->update([
                                    'anonymization_required' => $required,
                                    'anonymization_requirement_reviewed' => false,
                                    'changed_at' => $runAt,
                                    'changed_fields' => json_encode($changedFields, JSON_UNESCAPED_UNICODE),
                                    'updated_at' => $runAt,
                                ]);

                            $existingRequiredByColumn[$columnId] = $required;
                            $existingChangedFieldsByColumn[$columnId] = $changedFields;
                            $metrics['required_updates']++;
                            $rowChanged = true;
                            $requiredUpdated = true;
                            $changedColumns[$columnId] = true;
                        }
                    }
                }

                if (! $override && $anonNote === null) {
                    if (! $rowChanged) {
                        $metrics['rows_noop']++;
                    }
                    continue;
                }

                $requestedMethodNames = $this->parseAnonMethodNames($anonNote);
                $requestedMethodIds = [];
                foreach ($requestedMethodNames as $methodName) {
                    $methodId = $methodIdsByName[$this->norm($methodName)] ?? null;
                    if ($methodId) {
                        $requestedMethodIds[] = $methodId;
                    } else {
                        $metrics['unknown_method_names']++;

                        $methodKey = $this->norm($methodName);
                        $columnRef = implode('.', array_filter([
                            trim((string) ($row->database_name ?? '')),
                            trim((string) ($row->schema_name ?? '')),
                            trim((string) ($row->table_name ?? '')),
                            trim((string) ($row->column_name ?? '')),
                        ], fn($part) => $part !== ''));

                        if (! isset($unknownMethods[$methodKey])) {
                            $unknownMethods[$methodKey] = [
                                'method_name' => $methodName,
                                'count' => 0,
                                'examples' => [],
                            ];
                        }

                        $unknownMethods[$methodKey]['count']++;
                        if ($columnRef !== '' && count($unknownMethods[$methodKey]['examples']) < 5) {
                            $unknownMethods[$methodKey]['examples'][$columnRef] = true;
                        }
                    }
                }

                $requestedMethodIds = array_values(array_unique($requestedMethodIds));
                sort($requestedMethodIds);

                // Non-override imports are non-destructive when note contains unknown names only.
                if (! $override && $anonNote !== null && $requestedMethodNames !== [] && $requestedMethodIds === []) {
                    if (! $rowChanged) {
                        $metrics['rows_noop']++;
                    }
                    continue;
                }

                $currentMethodIds = $existingMethodIdsByColumn[$columnId] ?? [];
                sort($currentMethodIds);

                $methodIdsToDelete = array_values(array_diff($currentMethodIds, $requestedMethodIds));
                $methodIdsToInsert = array_values(array_diff($requestedMethodIds, $currentMethodIds));

                if ($methodIdsToDelete === [] && $methodIdsToInsert === []) {
                    if (! $rowChanged) {
                        $metrics['rows_noop']++;
                    }
                    continue;
                }

                if ($methodIdsToDelete !== []) {
                    DB::table('anonymization_method_column')
                        ->where('column_id', $columnId)
                        ->whereIn('method_id', $methodIdsToDelete)
                        ->delete();

                    $metrics['method_links_deleted'] += count($methodIdsToDelete);
                }

                $metrics['method_mapping_updates']++;
                $rowChanged = true;
                $changedColumns[$columnId] = true;

                if ($methodIdsToInsert !== []) {
                    $methodRows = [];
                    foreach ($methodIdsToInsert as $methodId) {
                        $methodRows[] = [
                            'column_id' => $columnId,
                            'method_id' => $methodId,
                            'created_at' => $runAt,
                            'updated_at' => $runAt,
                        ];
                    }

                    DB::table('anonymization_method_column')->insert($methodRows);
                    $metrics['method_links_inserted'] += count($methodIdsToInsert);
                }

                if (! $requiredUpdated) {
                    $changedFields = $existingChangedFieldsByColumn[$columnId] ?? [];
                    $changedFields['anonymization_methods'] = [
                        'old' => array_values($currentMethodIds),
                        'new' => array_values($requestedMethodIds),
                    ];

                    DB::table(self::COLUMNS_TABLE)
                        ->where('id', $columnId)
                        ->update([
                            'anonymization_requirement_reviewed' => false,
                            'changed_at' => $runAt,
                            'changed_fields' => json_encode($changedFields, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $runAt,
                        ]);

                    $existingChangedFieldsByColumn[$columnId] = $changedFields;
                } else {
                    $changedFields = $existingChangedFieldsByColumn[$columnId] ?? [];
                    $changedFields['anonymization_methods'] = [
                        'old' => array_values($currentMethodIds),
                        'new' => array_values($requestedMethodIds),
                    ];

                    DB::table(self::COLUMNS_TABLE)
                        ->where('id', $columnId)
                        ->update([
                            'changed_at' => $runAt,
                            'changed_fields' => json_encode($changedFields, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $runAt,
                        ]);

                    $existingChangedFieldsByColumn[$columnId] = $changedFields;
                }

                $existingMethodIdsByColumn[$columnId] = $requestedMethodIds;
            }
        }, 's.id', 'staging_id');

        foreach ($unknownMethods as $entry) {
            $methodName = (string) ($entry['method_name'] ?? '');
            if ($methodName === '') {
                continue;
            }

            $title = 'Missing anonymization method from CSV: ' . $methodName;
            $exists = ChangeTicket::query()
                ->where('upload_id', $upload->id)
                ->where('scope_type', 'upload')
                ->where('title', $title)
                ->whereIn('status', ['open', 'in_progress'])
                ->exists();

            if ($exists) {
                continue;
            }

            $examples = array_keys((array) ($entry['examples'] ?? []));
            $comment = 'CSV requested anonymization method "' . $methodName . '" but no matching method exists in the method library. Create this method and map its SQL behavior, then re-run/import to apply it.';

            if ($examples !== []) {
                $comment .= ' Example columns: ' . implode(', ', array_slice($examples, 0, 5)) . '.';
            }

            ChangeTicket::create([
                'title' => $title,
                'status' => 'open',
                'priority' => 'high',
                'severity' => 'medium',
                'scope_type' => 'upload',
                'scope_name' => (string) ($upload->original_name ?: $upload->id),
                'impact_summary' => $comment,
                'diff_payload' => json_encode([
                    'requested_method' => $methodName,
                    'requested_count' => (int) ($entry['count'] ?? 0),
                    'example_columns' => $examples,
                ]),
                'upload_id' => $upload->id,
            ]);
        }

        return [
            'changed_columns' => count($changedColumns),
            'required_updates' => (int) $metrics['required_updates'],
            'method_mapping_updates' => (int) $metrics['method_mapping_updates'],
        ];
    }

    protected function normalizeChangedFieldsPayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (! is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function parseAnonRuleRequiredFlag(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->norm($value);

        if (in_array($normalized, ['Y', 'YES', 'TRUE', '1', 'REQUIRED'], true)) {
            return true;
        }

        if (in_array($normalized, ['N', 'NO', 'FALSE', '0', 'NOT REQUIRED', 'NOT_REQUIRED'], true)) {
            return false;
        }

        return null;
    }

    protected function parseAnonMethodNames(?string $value): array
    {
        if ($value === null) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/\s*(?:;|,|\||\r\n|\n)\s*/', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $names = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name === '') {
                continue;
            }

            $names[$this->norm($name)] = $name;
        }

        return array_values($names);
    }

    protected function cleanupStaging(int $uploadId): void
    {
        DB::statement('DELETE FROM ' . self::STAGING_TABLE . ' WHERE upload_id = ?', [$uploadId]);
    }

    /**
     * Resolves or creates a database record for the provided name.
     */
    protected function resolveDatabaseId(string $databaseName, $now, array &$cache): int
    {
        $key = $this->norm($databaseName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $hash = $this->hashFor([
            'database_name' => $databaseName,
        ]);

        $record = DB::table(self::DATABASES_TABLE)
            ->where('database_name', $databaseName)
            ->first();

        if ($record) {
            $updates = [
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if ($record->content_hash !== $hash) {
                $updates['content_hash'] = $hash;
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode([
                    'content_hash' => [
                        'old' => $record->content_hash,
                        'new' => $hash,
                    ],
                ]);
            }

            DB::table(self::DATABASES_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::DATABASES_TABLE)->insertGetId([
            'database_name' => $databaseName,
            'description' => null,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a schema record under a database.
     */
    protected function resolveSchemaId(int $databaseId, string $schemaName, $now, array &$cache): int
    {
        $key = $this->norm($schemaName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $hash = $this->hashFor([
            'schema_name' => $schemaName,
            'database_id' => $databaseId,
        ]);

        $record = DB::table(self::SCHEMAS_TABLE)
            ->where('schema_name', $schemaName)
            ->where('database_id', $databaseId)
            ->first();

        if ($record) {
            $updates = [
                'database_id' => $databaseId,
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            if ($record->content_hash !== $hash) {
                $updates['content_hash'] = $hash;
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode([
                    'database_id' => [
                        'old' => $record->database_id,
                        'new' => $databaseId,
                    ],
                ]);
            }

            DB::table(self::SCHEMAS_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::SCHEMAS_TABLE)->insertGetId([
            'database_id' => $databaseId,
            'schema_name' => $schemaName,
            'description' => null,
            'type' => null,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a table record, updating metadata and comments as needed.
     */
    protected function resolveTableId(
        int $schemaId,
        string $tableName,
        ?string $objectType,
        ?string $tableComment,
        $now,
        array &$cache
    ): int {
        $key = $schemaId . '|' . $this->norm($tableName);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $object = $objectType ? strtolower($objectType) : 'table';
        $hash = $this->hashFor([
            'table_name' => $tableName,
            'schema_id' => $schemaId,
            'object_type' => $object,
            'table_comment' => $tableComment,
        ]);

        $record = DB::table(self::TABLES_TABLE)
            ->where('schema_id', $schemaId)
            ->where('table_name', $tableName)
            ->first();

        if ($record) {
            $updates = [
                'object_type' => $object,
                'table_comment' => $tableComment,
                'content_hash' => $hash,
                'last_synced_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $diff = $this->diffValues($record, $updates, ['object_type', 'table_comment', 'content_hash']);
            if ($diff !== []) {
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode($diff, JSON_UNESCAPED_UNICODE);
            }

            DB::table(self::TABLES_TABLE)
                ->where('id', $record->id)
                ->update($updates);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::TABLES_TABLE)->insertGetId([
            'schema_id' => $schemaId,
            'object_type' => $object,
            'table_name' => $tableName,
            'table_comment' => $tableComment,
            'content_hash' => $hash,
            'last_synced_at' => $now,
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Resolves or creates a data type reference for the column.
     */
    protected function resolveDataTypeId(?string $dataType, $now, array &$cache): ?int
    {
        if ($dataType === null || $dataType === '') {
            return null;
        }

        $key = $this->norm($dataType);
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $record = DB::table(self::DATA_TYPES_TABLE)
            ->where('data_type_name', $dataType)
            ->first();

        if ($record) {
            DB::table(self::DATA_TYPES_TABLE)
                ->where('id', $record->id)
                ->update([
                    'updated_at' => $now,
                    'deleted_at' => null,
                ]);

            return $cache[$key] = (int) $record->id;
        }

        $id = DB::table(self::DATA_TYPES_TABLE)->insertGetId([
            'data_type_name' => $dataType,
            'description' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $cache[$key] = $id;
    }

    /**
     * Inserts or updates a canonical column record from the staging payload.
     */
    protected function upsertColumn(int $tableId, ?int $dataTypeId, object $row, $now, array &$columnCache = []): array
    {
        $columnName = trim($row->column_name);
        $cacheKey = $this->norm($columnName);

        $existing = $columnCache[$tableId][$cacheKey] ?? null;

        if (! $existing) {
            $existing = DB::table(self::COLUMNS_TABLE)
                ->where('table_id', $tableId)
                ->where('column_name', $columnName)
                ->first();

            if ($existing) {
                $columnCache[$tableId][$cacheKey] = $existing;
            }
        }

        $relationships = $this->extractRelationshipsFromRow($row);
        $relationshipsJson = $relationships ? json_encode($relationships, JSON_UNESCAPED_UNICODE) : null;

        $payload = [
            'table_id' => $tableId,
            'column_name' => $columnName,
            'qualfield' => $row->qualfield,
            'column_id' => $row->column_id,
            'pr_key' => $row->pr_key,
            'ref_tab_name' => $row->ref_tab_name,
            'num_distinct' => $row->num_distinct,
            'num_not_null' => $row->num_not_null,
            'num_nulls' => $row->num_nulls,
            'num_rows' => $row->num_rows,
            'data_type_id' => $dataTypeId,
            'data_length' => $row->data_length,
            'data_precision' => $row->data_precision,
            'data_scale' => $row->data_scale,
            'nullable' => $this->toNullableFlag($row->nullable),
            'char_length' => $row->char_length,
            'column_comment' => $row->column_comment,
            'sbl_user_name' => $row->sbl_user_name,
            'sbl_desc_text' => $row->sbl_desc_text,
            'table_comment' => $row->table_comment,
            'related_columns_raw' => $row->related_columns_raw,
            'related_columns' => $relationshipsJson,
            'content_hash' => $row->content_hash,
            'last_synced_at' => $now,
        ];

        if ($existing) {
            $diff = $this->diffValues($existing, $payload, array_keys(Arr::except($payload, ['table_id', 'column_name'])));

            $updates = $payload;
            $updates['updated_at'] = $now;
            $updates['deleted_at'] = null;

            if ($diff !== []) {
                $updates['changed_at'] = $now;
                $updates['changed_fields'] = json_encode($diff, JSON_UNESCAPED_UNICODE);
                $updates['anonymization_requirement_reviewed'] = false;
            }

            DB::table(self::COLUMNS_TABLE)
                ->where('id', $existing->id)
                ->update($updates);

            $updatedRecord = clone $existing;
            foreach ($updates as $field => $value) {
                $updatedRecord->{$field} = $value;
            }
            $columnCache[$tableId][$cacheKey] = $updatedRecord;

            $wasResurrected = $existing->deleted_at !== null;

            if ($wasResurrected) {
                AnonymizerActivityLogger::logColumnEvent(
                    (int) $existing->id,
                    'restored',
                    [
                        'deleted_at' => [
                            'old' => $existing->deleted_at,
                            'new' => null,
                        ],
                    ],
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );
            }

            if ($diff !== []) {
                AnonymizerActivityLogger::logColumnEvent(
                    (int) $existing->id,
                    'updated',
                    $diff,
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );
            }

            return [
                'id' => (int) $existing->id,
                'inserted' => 0,
                'updated' => ($diff !== [] || $wasResurrected) ? 1 : 0,
            ];
        }

        $id = DB::table(self::COLUMNS_TABLE)->insertGetId(array_merge($payload, [
            'changed_at' => null,
            'changed_fields' => null,
            'deleted_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $record = (object) array_merge($payload, [
            'id' => $id,
            'deleted_at' => null,
            'changed_at' => null,
            'changed_fields' => null,
        ]);
        $columnCache[$tableId][$cacheKey] = $record;

        AnonymizerActivityLogger::logColumnEvent(
            $id,
            'created',
            [],
            [
                'upload_id' => $this->uploadId,
            ]
        );

        return [
            'id' => $id,
            'inserted' => 1,
            'updated' => 0,
        ];
    }

    /**
     * Chooses the best relationship representation available for a row.
     */
    protected function extractRelationshipsFromRow(object $row): array
    {
        $relationships = [];

        // Prefer decoded JSON structure if present
        if ($row->related_columns) {
            $decoded = json_decode($row->related_columns, true);
            if (is_array($decoded)) {
                $relationships = array_values(array_filter($decoded, fn($item) => is_array($item)));
            }
        }

        // Fallback: parse raw text
        if ($relationships === []) {
            if (! $row->related_columns_raw) {
                return [];
            }
            $relationships = $this->parseRelated((string) $row->related_columns_raw) ?: [];
        }

        // Normalize "descriptor"-only items into structured triplets using row context.
        // Heuristic: treat descriptor as a table name in the same schema; default target column to ROW_ID; OUTBOUND edge.
        $schemaName = isset($row->schema_name) ? (string) $row->schema_name : SiebelMetadata::DEFAULT_SCHEMA;
        $normalized = [];
        foreach ($relationships as $rel) {
            if (isset($rel['schema'], $rel['table'], $rel['column'])) {
                // Already structured; keep as-is
                $normalized[] = [
                    'direction' => strtoupper($rel['direction'] ?? 'OUTBOUND'),
                    'schema' => (string) $rel['schema'],
                    'table' => (string) $rel['table'],
                    'column' => (string) $rel['column'],
                    'constraint' => $rel['constraint'] ?? null,
                ];
                continue;
            }

            $descriptor = null;
            if (is_array($rel) && isset($rel['descriptor'])) {
                $descriptor = trim((string) $rel['descriptor']);
            } elseif (is_string($rel)) {
                $descriptor = trim($rel);
            }

            if ($descriptor === null || $descriptor === '') {
                continue;
            }

            // If descriptor looks like schema.table.column, try to split
            if (preg_match('/^([^.]+)\.([^.]+)\.([^\s]+)$/', $descriptor, $m)) {
                $normalized[] = [
                    'direction' => 'OUTBOUND',
                    'schema' => trim($m[1]),
                    'table' => trim($m[2]),
                    'column' => trim($m[3]),
                    'constraint' => null,
                ];
                continue;
            }

            // Otherwise, assume descriptor is a table name (possibly with a suffix token); target PK column ROW_ID.
            $tableCandidate = $descriptor;
            $normalized[] = [
                'direction' => 'OUTBOUND',
                'schema' => $schemaName,
                'table' => $tableCandidate,
                'column' => 'ROW_ID',
                'constraint' => null,
            ];

            // Heuristic: if descriptor has a suffix token (e.g., S_FN_AAGSVC_CON), also try without the final token.
            if (str_contains($tableCandidate, '_')) {
                $parts = preg_split('/_+/', $tableCandidate) ?: [];
                if (count($parts) > 1) {
                    $alt = implode('_', array_slice($parts, 0, -1));
                    if ($alt !== '' && $alt !== $tableCandidate) {
                        $normalized[] = [
                            'direction' => 'OUTBOUND',
                            'schema' => $schemaName,
                            'table' => $alt,
                            'column' => 'ROW_ID',
                            'constraint' => null,
                        ];
                    }
                }
            }
        }

        return $normalized;
    }

    /**
     * Soft deletes any existing columns that were not present in the latest upload.
     *
     * @param array $touchedTableIdentities Array of table identities that were touched
     * @param string $tempTableName Name of temporary table containing processed column identities
     * @param mixed $now Timestamp for the deletion
     */
    protected function softDeleteMissingColumns(array $touchedTableIdentities, string $processedColumnsTempTable, $now): int
    {
        if ($touchedTableIdentities === []) {
            return 0;
        }

        $deleted = 0;
        $identityFilters = array_values($touchedTableIdentities);

        $tempTableIdentitiesTable = 'temp_table_identities_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempTableIdentitiesTable} (
            database_id INTEGER NOT NULL,
            schema_name VARCHAR(255) NOT NULL,
            table_name VARCHAR(255) NOT NULL
        )");

        foreach (array_chunk($identityFilters, 20000) as $chunk) {
            $insertData = [];
            foreach ($chunk as $filter) {
                $insertData[] = [
                    'database_id' => $filter['database_id'],
                    'schema_name' => $filter['schema_name'],
                    'table_name' => $filter['table_name'],
                ];
            }
            if ($insertData !== []) {
                DB::table($tempTableIdentitiesTable)->insert($insertData);
            }
        }

        $tempMatchedTablesTable = 'temp_matched_tables_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempMatchedTablesTable} (table_id INTEGER PRIMARY KEY)");

        DB::statement("
            INSERT INTO {$tempMatchedTablesTable} (table_id)
            SELECT t.id
            FROM " . self::TABLES_TABLE . " t
            INNER JOIN " . self::SCHEMAS_TABLE . " s ON t.schema_id = s.id
            INNER JOIN {$tempTableIdentitiesTable} ti
                ON s.database_id = ti.database_id
                AND s.schema_name = ti.schema_name
                AND t.table_name = ti.table_name
        ");

        if (DB::table($tempMatchedTablesTable)->count() === 0) {
            DB::statement("DROP TABLE IF EXISTS {$tempTableIdentitiesTable}");
            DB::statement("DROP TABLE IF EXISTS {$tempMatchedTablesTable}");
            return 0;
        }

        $tempColumnsToDeleteTable = 'temp_columns_to_delete_' . uniqid();
        DB::statement("CREATE TEMPORARY TABLE {$tempColumnsToDeleteTable} (column_id INTEGER PRIMARY KEY)");

        DB::statement("
            INSERT INTO {$tempColumnsToDeleteTable} (column_id)
            SELECT c.id
            FROM " . self::COLUMNS_TABLE . " c
            INNER JOIN {$tempMatchedTablesTable} mt ON c.table_id = mt.table_id
            LEFT JOIN {$processedColumnsTempTable} processed
                ON processed.table_id = c.table_id
                AND processed.column_name = UPPER(TRIM(c.column_name))
            WHERE c.deleted_at IS NULL
              AND processed.table_id IS NULL
        ");

        $lastDeletedId = 0;
        $deleteChunkSize = 100;

        do {
            $columnsToProcess = DB::table(self::COLUMNS_TABLE . ' as c')
                ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
                ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
                ->join($tempColumnsToDeleteTable . ' as del', 'c.id', '=', 'del.column_id')
                ->select('c.id', 's.database_id', 's.schema_name', 't.table_name', 'c.column_name')
                ->where('c.id', '>', $lastDeletedId)
                ->orderBy('c.id')
                ->limit($deleteChunkSize)
                ->get();

            if ($columnsToProcess->isEmpty()) {
                break;
            }

            $columnIds = $columnsToProcess->pluck('id')->map(fn($id) => (int) $id)->all();
            $diff = [
                'deleted_at' => [
                    'old' => null,
                    'new' => $now,
                ],
            ];

            DB::table(self::COLUMNS_TABLE)
                ->whereIn('id', $columnIds)
                ->update([
                    'deleted_at' => $now,
                    'changed_at' => $now,
                    'changed_fields' => json_encode($diff, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);

            foreach ($columnsToProcess as $column) {
                AnonymizerActivityLogger::logColumnEvent(
                    (int) $column->id,
                    'deleted',
                    $diff,
                    [
                        'upload_id' => $this->uploadId,
                    ]
                );

                $lastDeletedId = $column->id;
                ++$deleted;
            }
        } while (true);

        DB::statement("DROP TABLE IF EXISTS {$tempColumnsToDeleteTable}");
        DB::statement("DROP TABLE IF EXISTS {$tempMatchedTablesTable}");
        DB::statement("DROP TABLE IF EXISTS {$tempTableIdentitiesTable}");

        return $deleted;
    }
    /**
     * Rebuild column relationships from a temporary table containing column IDs.
     * Processes in chunks to avoid memory exhaustion.
     *
     * @param string $tempTableName Name of temporary table containing column IDs
     * @param CarbonImmutable $runAt Timestamp for the update
     */
    protected function rebuildColumnRelationships(string $tempTableName, CarbonImmutable $runAt): void
    {
        $chunkSize = 1000;
        $processedChunks = 0;

        // Process column IDs from temp table in chunks
        DB::table($tempTableName)
            ->orderBy('column_id')
            ->chunk($chunkSize, function ($tempRows) use ($runAt, &$processedChunks) {
                $chunkIds = $tempRows->pluck('column_id')->map(fn($id) => (int) $id)->all();

                if (empty($chunkIds)) {
                    return;
                }

                $this->processColumnRelationshipsChunk($chunkIds, $runAt);

                // Garbage collection every 10 chunks
                $processedChunks++;
                if ($processedChunks % 10 === 0) {
                    gc_collect_cycles();
                }
            });
    }

    /**
     * Process a chunk of column IDs for relationship rebuilding.
     */
    protected function processColumnRelationshipsChunk(array $chunkIds, CarbonImmutable $runAt): void
    {
        $rows = DB::table(self::COLUMNS_TABLE . ' as c')
            ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select(
                'c.id',
                'c.table_id',
                'c.column_name',
                'c.related_columns',
                'c.related_columns_raw',
                's.schema_name',
                't.table_name'
            )
            ->whereIn('c.id', $chunkIds)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $columnMeta = [];
        $relationshipsByColumn = [];
        $referencedColumns = [];
        $touchedColumnIds = [];

        foreach ($rows as $row) {
            $columnKey = $this->columnKey((int) $row->table_id, $row->column_name);

            $columnMeta[$columnKey] = [
                'id' => (int) $row->id,
                'table_id' => (int) $row->table_id,
                'schema_name' => $row->schema_name,
                'table_name' => $row->table_name,
                'column_name' => $row->column_name,
            ];

            $touchedColumnIds[(int) $row->id] = true;

            $relationships = $this->extractRelationshipsFromRow($row);
            if ($relationships === []) {
                continue;
            }

            $relationshipsByColumn[$columnKey] = $relationships;

            foreach ($relationships as $relation) {
                if (! isset($relation['schema'], $relation['table'], $relation['column'])) {
                    continue;
                }

                $referenceKey = $this->tripletKey($relation['schema'], $relation['table'], $relation['column']);

                if (! isset($referencedColumns[$referenceKey])) {
                    $referencedColumns[$referenceKey] = [
                        'schema' => $relation['schema'],
                        'table' => $relation['table'],
                        'column' => $relation['column'],
                    ];
                }
            }
        }

        $this->syncRelationships($columnMeta, $relationshipsByColumn, $referencedColumns, $touchedColumnIds, $runAt);
    }

    /**
     * Rebuilds dependency edges for every column touched during the sync.
     */
    protected function syncRelationships(
        array $columnMeta,
        array $relationshipsByColumn,
        array $referencedColumns,
        array $touchedColumnIds,
        $now
    ): void {
        if ($relationshipsByColumn === [] && $touchedColumnIds === []) {
            return;
        }

        $columnIndex = $this->buildColumnIndex($columnMeta, $referencedColumns);

        $touchedIds = array_keys($touchedColumnIds);
        if ($touchedIds !== []) {
            DB::table(self::DEPENDENCIES_TABLE)
                ->whereIn('child_field_id', $touchedIds)
                ->delete();
        }

        if ($relationshipsByColumn === []) {
            return;
        }

        $rows = [];
        $seen = [];

        foreach ($relationshipsByColumn as $columnKey => $relations) {
            $childId = $columnIndex['byKey'][$columnKey] ?? null;
            if (! $childId) {
                continue;
            }

            foreach ($relations as $relation) {
                if (! isset($relation['schema'], $relation['table'], $relation['column'])) {
                    continue;
                }

                $targetKey = $this->tripletKey($relation['schema'], $relation['table'], $relation['column']);
                $targetId = $columnIndex['byTriplet'][$targetKey] ?? null;
                if (! $targetId) {
                    continue;
                }

                $direction = strtoupper($relation['direction'] ?? 'OUTBOUND');

                if ($direction === 'OUTBOUND') {
                    $parentId = $targetId;
                    $childIdForRow = $childId;
                } else {
                    $parentId = $childId;
                    $childIdForRow = $targetId;
                }

                if (! $childIdForRow) {
                    continue;
                }

                $signature = ($parentId ?? 'null') . '|' . $childIdForRow;
                if (isset($seen[$signature])) {
                    continue;
                }
                $seen[$signature] = true;

                $rows[] = [
                    'parent_field_id' => $parentId,
                    'child_field_id' => $childIdForRow,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table(self::DEPENDENCIES_TABLE)->insert($rows);
        }
    }

    /**
     * Builds lookup maps to resolve column IDs from either local metadata or referenced triples.
     */
    protected function buildColumnIndex(array $columnMeta, array $referencedColumns): array
    {
        $byKey = [];
        $byTriplet = [];

        foreach ($columnMeta as $columnKey => $meta) {
            if (! isset($meta['id'])) {
                continue;
            }

            $byKey[$columnKey] = $meta['id'];
            $triplet = $this->tripletKey($meta['schema_name'], $meta['table_name'], $meta['column_name']);
            $byTriplet[$triplet] = $meta['id'];
        }

        $missing = array_diff_key($referencedColumns, $byTriplet);
        if ($missing === []) {
            return [

                'byKey' => $byKey,
                'byTriplet' => $byTriplet,
            ];
        }

        DB::table(self::COLUMNS_TABLE . ' as c')
            ->join(self::TABLES_TABLE . ' as t', 'c.table_id', '=', 't.id')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('c.id', 's.schema_name', 't.table_name', 'c.column_name')
            ->where(function ($query) use ($missing) {
                foreach ($missing as $ref) {
                    $query->orWhere(function ($nested) use ($ref) {
                        $nested
                            ->where('s.schema_name', $ref['schema'])
                            ->where('t.table_name', $ref['table'])
                            ->where('c.column_name', $ref['column']);
                    });
                }
            })
            ->orderBy('c.id')
            ->chunk(500, function ($chunk) use (&$byTriplet) {
                foreach ($chunk as $record) {
                    $triplet = $this->tripletKey($record->schema_name, $record->table_name, $record->column_name);
                    $byTriplet[$triplet] = (int) $record->id;
                }
            });

        return [
            'byKey' => $byKey,
            'byTriplet' => $byTriplet,
        ];
    }

    /**
     * Reads the header row from the provided CSV stream.
     */
    protected function readHeader($stream): ?array
    {
        $header = fgetcsv($stream);
        if ($header === false || $header === null) {
            return null;
        }

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]) ?? $header[0];
        }

        return array_map(function ($value) {
            return strtoupper(trim($this->normalizeUtf8String((string) $value, false)));
        }, $header);
    }

    /**
     * Parses relationship descriptors into structured arrays.
     */
    protected function parseRelated(string $raw): array
    {
        $raw = trim($this->normalizeUtf8String(html_entity_decode($raw), false));
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/\s*;\s*/', $raw) ?: [];
        $relationships = [];

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $pattern = '/^(INBOUND|OUTBOUND)\s*(<-|->)\s*([^.]+)\.([^.]+)\.([^\s]+)(?:\s+via\s+(\S+))?/i';
            if (preg_match($pattern, $part, $matches)) {
                $relationships[] = [
                    'direction' => strtoupper($matches[1]),
                    'arrow' => $matches[2],
                    'schema' => trim($matches[3]),
                    'table' => trim($matches[4]),
                    'column' => trim($matches[5], ','),
                    'constraint' => $matches[6] ?? null,
                ];
                continue;
            }

            $relationships[] = [
                'descriptor' => $part,
            ];
        }

        return $relationships;
    }

    /**
     * Produces a diff array describing changes between stored and new values.
     */
    protected function diffValues(object $existing, array $payload, array $fields): array
    {
        $diff = [];

        foreach ($fields as $field) {
            if ($field === 'changed_fields' || $field === 'changed_at') {
                continue;
            }

            $old = $existing->{$field} ?? null;
            $new = $payload[$field] ?? null;

            if ($this->valuesDiffer($old, $new)) {
                $diff[$field] = [
                    'old' => $old,
                    'new' => $new,
                ];
            }
        }

        return $diff;
    }

    /**
     * Determines whether two values differ while accounting for type juggling.
     */
    protected function valuesDiffer($old, $new): bool
    {
        if ($old === null && $new === null) {
            return false;
        }

        if (is_bool($old) || is_bool($new)) {
            return (bool) $old !== (bool) $new;
        }

        if (is_numeric($old) || is_numeric($new)) {
            return (float) $old !== (float) $new;
        }

        return (string) $old !== (string) $new;
    }

    /**
     * Typed helper utilities to normalize CSV scalar values.
     */
    protected function toInt($value): ?int
    {
        $value = is_string($value) ? trim($value) : $value;
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_string($value)) {
            // Handle common thousands separators from exports (e.g. "1,000").
            $candidate = str_replace([',', ' '], '', $value);
            if ($candidate !== '' && preg_match('/^\d+$/', $candidate)) {
                $value = $candidate;
            }
        }

        return (int) $value;
    }

    protected function toNullOrString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($this->normalizeUtf8String((string) $value, false));

        return $value === '' ? null : $value;
    }

    protected function sanitizeUtf8ForDatabase(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $result = $this->normalizeUtf8String($value);

        // Common broken UTF-8 triplet seen in legacy exports (0xE2 0x80 0x20).
        $result = str_replace("\xE2\x80\x20", ' ', $result);

        if (preg_match('//u', $result) !== 1 && function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $result);
            if ($converted !== false) {
                $result = $converted;
            }
        }

        if (preg_match('//u', $result) !== 1 && function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($result, 'UTF-8', 'UTF-8, Windows-1252, ISO-8859-1');
            if (is_string($converted)) {
                $result = $converted;
            }
        }

        if (preg_match('//u', $result) !== 1) {
            $ascii = '';
            foreach (unpack('C*', $result) ?: [] as $byte) {
                if (($byte >= 9 && $byte <= 13) || ($byte >= 32 && $byte <= 126)) {
                    $ascii .= chr($byte);
                }
            }
            $result = $ascii;
        }

        $result = str_replace("\u{FFFD}", '', $result);
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $result) ?? '';

        return preg_match('//u', $result) === 1 ? $result : '';
    }

    protected function normalizeUtf8String(string $value, bool $normalizeWhitespace = true): string
    {
        if ($value === '') {
            return $value;
        }

        $result = str_replace("\xA0", ' ', $value);

        if (preg_match('//u', $result) !== 1) {
            if (function_exists('iconv')) {
                $converted = iconv('Windows-1252', 'UTF-8//IGNORE', $result);
                if ($converted !== false && preg_match('//u', $converted) === 1) {
                    $result = $converted;
                }
            }

            if (preg_match('//u', $result) !== 1 && function_exists('mb_convert_encoding')) {
                $converted = mb_convert_encoding($result, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
                if (is_string($converted) && preg_match('//u', $converted) === 1) {
                    $result = $converted;
                }
            }

            if (preg_match('//u', $result) !== 1) {
                $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $result) ?? '';
            }
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $result);
            if ($converted !== false) {
                $result = $converted;
            }
        }

        if (preg_match('//u', $result) !== 1 && function_exists('mb_convert_encoding')) {
            $converted = mb_convert_encoding($result, 'UTF-8', 'UTF-8');
            if (is_string($converted)) {
                $result = $converted;
            }
        }

        if (preg_match('//u', $result) !== 1) {
            $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $result) ?? '';
        }

        if (preg_match('//u', $result) !== 1) {
            $ascii = '';
            foreach (unpack('C*', $result) ?: [] as $byte) {
                if (($byte >= 9 && $byte <= 13) || ($byte >= 32 && $byte <= 126)) {
                    $ascii .= chr($byte);
                }
            }
            $result = $ascii;
        }

        if ($normalizeWhitespace) {
            $result = str_replace(["\xA0", "\xC2\xA0", "\u{00A0}"], ' ', $result);
        }

        return $result;
    }

    protected function toNullableFlag($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        if (in_array($normalized, ['Y', 'YES', 'TRUE'], true)) {
            return true;
        }

        if (in_array($normalized, ['N', 'NO', 'FALSE'], true)) {
            return false;
        }

        return null;
    }

    /**
     * Hashing and key helpers shared across the sync pipeline.
     */
    protected function hashFor(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    protected function columnKey(int $tableId, string $columnName): string
    {
        return $tableId . '|' . $this->norm($columnName);
    }

    protected function tableIdentityKey(int $databaseId, string $schemaName, string $tableName): string
    {
        return $databaseId . '|' . $this->norm($schemaName) . '|' . $this->norm($tableName);
    }

    protected function columnIdentityKey(int $databaseId, string $schemaName, string $tableName, string $columnName): string
    {
        return $this->tableIdentityKey($databaseId, $schemaName, $tableName) . '|' . $this->norm($columnName);
    }

    protected function tripletKey(string $schema, string $table, string $column): string
    {
        return $this->norm($schema) . '|' . $this->norm($table) . '|' . $this->norm($column);
    }

    protected function norm(string $value): string
    {
        return Str::upper(trim($value));
    }

    protected function resolveFullImportDeletionScope(AnonymousUpload $upload, array $touchedTableIdentities): array
    {
        if (($upload->import_type ?? 'partial') !== 'full') {
            return $touchedTableIdentities;
        }

        $scope = strtolower((string) ($upload->scope_type ?? ''));
        $resolved = match ($scope) {
            '' => $this->fetchTableIdentitiesForStagedScopes((int) $upload->id),
            'database' => $this->fetchTableIdentitiesForDatabase($upload->scope_name),
            'schema' => $this->fetchTableIdentitiesForSchema($upload->scope_name),
            'table' => $this->fetchTableIdentitiesForTable($upload->scope_name),
            default => $touchedTableIdentities,
        };

        if ($resolved === []) {
            return $touchedTableIdentities;
        }

        return $resolved ?: $touchedTableIdentities;
    }

    protected function fetchTableIdentitiesForStagedScopes(int $uploadId): array
    {
        $stagedScopes = DB::table(self::STAGING_TABLE)
            ->where('upload_id', $uploadId)
            ->select('database_name', 'schema_name')
            ->distinct()
            ->get()
            ->map(function ($row) {
                return [
                    'database_name' => $this->norm((string) ($row->database_name ?? '')),
                    'schema_name' => $this->norm((string) ($row->schema_name ?? '')),
                ];
            })
            ->filter(fn(array $row) => $row['database_name'] !== '' && $row['schema_name'] !== '')
            ->values()
            ->all();

        if ($stagedScopes === []) {
            return [];
        }

        $query = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->join(self::DATABASES_TABLE . ' as d', 's.database_id', '=', 'd.id')
            ->select('s.database_id', 's.schema_name', 't.table_name')
            ->whereNull('t.deleted_at')
            ->where(function ($where) use ($stagedScopes) {
                foreach ($stagedScopes as $scope) {
                    $where->orWhere(function ($nested) use ($scope) {
                        $nested
                            ->whereRaw('UPPER(TRIM(d.database_name)) = ?', [$scope['database_name']])
                            ->whereRaw('UPPER(TRIM(s.schema_name)) = ?', [$scope['schema_name']]);
                    });
                }
            });

        return $this->mapTableIdentityRows($query->get());
    }

    protected function fetchAllTableIdentities(): array
    {
        $rows = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('s.database_id', 's.schema_name', 't.table_name')
            ->whereNull('t.deleted_at')
            ->get();

        return $this->mapTableIdentityRows($rows);
    }

    protected function fetchTableIdentitiesForDatabase(?string $databaseName): array
    {
        if (! is_string($databaseName) || trim($databaseName) === '') {
            return [];
        }

        $rows = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->join(self::DATABASES_TABLE . ' as d', 's.database_id', '=', 'd.id')
            ->select('s.database_id', 's.schema_name', 't.table_name')
            ->whereNull('t.deleted_at')
            ->whereRaw('UPPER(TRIM(d.database_name)) = ?', [$this->norm($databaseName)])
            ->get();

        return $this->mapTableIdentityRows($rows);
    }

    protected function fetchTableIdentitiesForSchema(?string $schemaIdentifier): array
    {
        if (! is_string($schemaIdentifier) || trim($schemaIdentifier) === '') {
            return [];
        }

        $databaseFilter = null;
        $schemaName = $schemaIdentifier;

        if (str_contains($schemaIdentifier, '.')) {
            [$databaseFilter, $schemaName] = array_map('trim', explode('.', $schemaIdentifier, 2));
        }

        $query = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('s.database_id', 's.schema_name', 't.table_name')
            ->whereNull('t.deleted_at')
            ->whereRaw('UPPER(TRIM(s.schema_name)) = ?', [$this->norm($schemaName)]);

        if ($databaseFilter && $databaseFilter !== '') {
            $query->join(self::DATABASES_TABLE . ' as d', 's.database_id', '=', 'd.id')
                ->whereRaw('UPPER(TRIM(d.database_name)) = ?', [$this->norm($databaseFilter)]);
        }

        return $this->mapTableIdentityRows($query->get());
    }

    protected function fetchTableIdentitiesForTable(?string $tableIdentifier): array
    {
        if (! is_string($tableIdentifier) || trim($tableIdentifier) === '') {
            return [];
        }

        $schemaFilter = null;
        $tableName = $tableIdentifier;

        if (str_contains($tableIdentifier, '.')) {
            [$schemaFilter, $tableName] = array_map('trim', explode('.', $tableIdentifier, 2));
        }

        $query = DB::table(self::TABLES_TABLE . ' as t')
            ->join(self::SCHEMAS_TABLE . ' as s', 't.schema_id', '=', 's.id')
            ->select('s.database_id', 's.schema_name', 't.table_name')
            ->whereNull('t.deleted_at')
            ->whereRaw('UPPER(TRIM(t.table_name)) = ?', [$this->norm($tableName)]);

        if ($schemaFilter && $schemaFilter !== '') {
            $query->whereRaw('UPPER(TRIM(s.schema_name)) = ?', [$this->norm($schemaFilter)]);
        }

        return $this->mapTableIdentityRows($query->get());
    }

    protected function mapTableIdentityRows($rows): array
    {
        $identities = [];

        foreach ($rows as $row) {
            if ($row->database_id === null || $row->schema_name === null || $row->table_name === null) {
                continue;
            }

            $key = $this->tableIdentityKey((int) $row->database_id, $row->schema_name, $row->table_name);
            $identities[$key] = [
                'database_id' => (int) $row->database_id,
                'schema_name' => $row->schema_name,
                'table_name' => $row->table_name,
            ];
        }

        return array_values($identities);
    }
}
