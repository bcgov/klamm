<?php

namespace App\Models\Anonymizer;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AnonymizationJobs extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymization_jobs';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->name ?: ('#' . $this->getKey());
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $job = $this->name ?: ('#' . $this->getKey());

        return match ($eventName) {
            'created' => "Anonymization job {$job} created",
            'deleted' => "Anonymization job {$job} deleted",
            'restored' => "Anonymization job {$job} restored",
            'updated' => "Anonymization job {$job} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';

    public const OUTPUT_SQL = 'sql';
    public const OUTPUT_PARQUET = 'parquet';

    protected $fillable = [
        'name',
        'job_type',
        'status',
        'output_format',
        'strategy',
        'target_relation_kind',
        'target_schema',
        'target_table_mode',
        'seed_store_mode',
        'seed_store_schema',
        'seed_store_prefix',
        'seed_map_hygiene_mode',
        'job_seed',
        'pre_mask_sql',
        'post_mask_sql',
        'last_run_at',
        'duration_seconds',
        'sql_script',
    ];

    protected $casts = [
        'id' => 'integer',
        'last_run_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT];

    protected $appends = ['duration_human'];

    public function databases(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelDatabase::class,
            'anonymization_job_databases',
            'job_id',
            'database_id'
        )->withTimestamps();
    }

    public function schemas(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelSchema::class,
            'anonymization_job_schemas',
            'job_id',
            'schema_id'
        )->withTimestamps();
    }

    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelTable::class,
            'anonymization_job_tables',
            'job_id',
            'table_id'
        )->withTimestamps();
    }

    public function columns(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelColumn::class,
            'anonymization_job_columns',
            'job_id',
            'column_id'
        )->withPivot('anonymization_method_id')
            ->withTimestamps()
            ->orderBy('anonymous_siebel_columns.column_name');
    }

    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationMethods::class,
            'anonymization_job_columns',
            'job_id',
            'anonymization_method_id'
        )
            ->withPivot('column_id')
            ->withTimestamps()
            ->wherePivotNotNull('anonymization_method_id')
            ->orderBy('anonymization_methods.name');
    }

    public function getDurationHumanAttribute(): ?string
    {
        $seconds = $this->getAttribute('duration_seconds');

        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $parts = [];

        if ($hours > 0) {
            $parts[] = sprintf('%dh', $hours);
        }

        if ($minutes > 0 || empty($parts)) {
            $parts[] = sprintf('%dm', $minutes);
        }

        return implode(' ', $parts);
    }

    public function duplicateAsDraft(?string $name = null): self
    {
        return DB::transaction(function () use ($name): self {
            $duplicate = new self();
            $duplicate->fill(Arr::only($this->getAttributes(), $this->getFillable()));

            $duplicate->name = $name ?: $this->nextDuplicateName();
            $duplicate->status = self::STATUS_DRAFT;
            $duplicate->last_run_at = null;
            $duplicate->duration_seconds = null;
            $duplicate->sql_script = null;
            $duplicate->save();

            $this->copySimplePivotRows('anonymization_job_databases', 'database_id', (int) $duplicate->getKey());
            $this->copySimplePivotRows('anonymization_job_schemas', 'schema_id', (int) $duplicate->getKey());
            $this->copySimplePivotRows('anonymization_job_tables', 'table_id', (int) $duplicate->getKey());
            $this->copyColumnPivotRows((int) $duplicate->getKey());

            return $duplicate;
        });
    }

    protected function nextDuplicateName(): string
    {
        $baseName = trim((string) $this->name);
        $baseName = $baseName !== '' ? $baseName : ('Job ' . $this->getKey());

        $candidate = $baseName . ' (Copy)';

        if (! static::withTrashed()->where('name', $candidate)->exists()) {
            return $candidate;
        }

        $suffix = 2;

        do {
            $candidate = sprintf('%s (Copy %d)', $baseName, $suffix);
            $suffix++;
        } while (static::withTrashed()->where('name', $candidate)->exists());

        return $candidate;
    }

    protected function copySimplePivotRows(string $table, string $foreignKeyColumn, int $newJobId): void
    {
        $timestamp = now()->toDateTimeString();

        DB::table($table)->insertUsing(
            ['job_id', $foreignKeyColumn, 'created_at', 'updated_at'],
            DB::table($table)
                ->where('job_id', $this->getKey())
                ->selectRaw('? as job_id, ' . $foreignKeyColumn . ', ? as created_at, ? as updated_at', [
                    $newJobId,
                    $timestamp,
                    $timestamp,
                ])
        );
    }

    protected function copyColumnPivotRows(int $newJobId): void
    {
        $timestamp = now()->toDateTimeString();

        DB::table('anonymization_job_columns')->insertUsing(
            ['job_id', 'column_id', 'anonymization_method_id', 'created_at', 'updated_at'],
            DB::table('anonymization_job_columns')
                ->where('job_id', $this->getKey())
                ->selectRaw('? as job_id, column_id, anonymization_method_id, ? as created_at, ? as updated_at', [
                    $newJobId,
                    $timestamp,
                    $timestamp,
                ])
        );
    }
}
