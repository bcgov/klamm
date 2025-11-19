<?php

namespace App\Models;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymousSiebelDatabase;
use App\Models\Anonymizer\AnonymousSiebelSchema;
use App\Models\Anonymizer\AnonymousSiebelTable;
use App\Models\AnonymizationMethods;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnonymizationJobs extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const TYPE_FULL = 'full';
    public const TYPE_PARTIAL = 'partial';

    public const OUTPUT_SQL = 'sql';
    public const OUTPUT_PARQUET = 'parquet';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'job_type',
        'status',
        'output_format',
        'last_run_at',
        'duration_seconds',
        'sql_script',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'last_run_at' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    /**
     * Track the methods used in the job to support quick UI summaries.
     *
     * @var array<int, string>
     */
    protected $with = ['methods'];

    /**
     * @var array<int, string>
     */
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
        )->withPivot('column_id')
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
}
