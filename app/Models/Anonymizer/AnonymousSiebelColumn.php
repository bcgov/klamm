<?php

namespace App\Models\Anonymizer;

use App\Enums\SeedContractMode;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationMethods;
use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelColumn extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymous_siebel_columns';

    protected $appends = ['seed_contract_summary'];

    protected $fillable = [
        'column_name',
        'column_id',
        'data_length',
        'data_precision',
        'data_scale',
        'nullable',
        'char_length',
        'column_comment',
        'table_comment',
        'related_columns_raw',
        'related_columns',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
        'table_id',
        'data_type_id',
        'metadata_comment',
        'anonymization_required',
        'anonymization_requirement_reviewed',
        'seed_contract_mode',
        'seed_contract_expression',
        'seed_contract_notes',
    ];

    protected $casts = [
        'column_id' => 'integer',
        'data_length' => 'integer',
        'data_precision' => 'integer',
        'data_scale' => 'integer',
        'nullable' => 'boolean',
        'char_length' => 'integer',
        'related_columns' => 'array',
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
        'anonymization_required' => 'boolean',
        'anonymization_requirement_reviewed' => 'boolean',
        'seed_contract_mode' => SeedContractMode::class,
    ];

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymous_siebel_columns';
    }

    public function getSeedContractSummaryAttribute(): string
    {
        $mode = $this->seed_contract_mode;

        if (! $mode) {
            return 'Not declared';
        }

        $suffix = [];

        if ($this->seed_contract_expression) {
            $suffix[] = 'expression defined';
        }

        if ($this->seed_contract_notes) {
            $suffix[] = 'notes';
        }

        $summary = $mode->label();

        if ($suffix !== []) {
            $summary .= ' (' . implode(', ', $suffix) . ')';
        }

        return $summary;
    }

    protected static function activityLogAttributesOverride(): ?array
    {
        return [
            'table_id',
            'data_type_id',
            'column_name',
            'column_id',
            'data_length',
            'data_precision',
            'data_scale',
            'nullable',
            'char_length',
            'column_comment',
            'table_comment',
            'related_columns_raw',
            'related_columns',
            'content_hash',
            'last_synced_at',
            'changed_at',
            'anonymization_required',
            'anonymization_requirement_reviewed',
            'metadata_comment',
        ];
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->column_name;
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $qualifiedTable = $this->resolveQualifiedTableName() ?? 'unknown table';
        $column = $this->column_name ?? ('#' . $this->getKey());

        $fields = isset($context['diff']) && $context['diff'] !== []
            ? implode(', ', array_keys($context['diff']))
            : null;

        return match ($eventName) {
            'created' => "Column {$column} added to {$qualifiedTable}",
            'deleted' => "Column {$column} removed from {$qualifiedTable}",
            'restored' => "Column {$column} restored on {$qualifiedTable}",
            'updated' => $fields
                ? "Column {$column} updated on {$qualifiedTable} ({$fields})"
                : "Column {$column} updated on {$qualifiedTable}",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    public function table()
    {
        return $this->belongsTo(AnonymousSiebelTable::class, 'table_id')->withTrashed();
    }

    public function dataType()
    {
        return $this->belongsTo(AnonymousSiebelDataType::class, 'data_type_id')->withTrashed();
    }

    public function childColumns()
    {
        return $this->belongsToMany(self::class, 'anonymous_siebel_column_dependencies', 'parent_field_id', 'child_field_id')
            ->withPivot(['seed_bundle_label', 'seed_bundle_components', 'is_seed_mandatory'])
            ->withTimestamps();
    }

    public function parentColumns()
    {
        return $this->belongsToMany(self::class, 'anonymous_siebel_column_dependencies', 'child_field_id', 'parent_field_id')
            ->withPivot(['seed_bundle_label', 'seed_bundle_components', 'is_seed_mandatory'])
            ->withTimestamps();
    }

    public function anonymizationMethods(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationMethods::class,
            'anonymization_method_column',
            'column_id',
            'method_id'
        )->withTimestamps();
    }

    public function anonymizationJobs(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationJobs::class,
            'anonymization_job_columns',
            'column_id',
            'job_id'
        )->withPivot('anonymization_method_id')
            ->withTimestamps();
    }

    private function resolveQualifiedTableName(): ?string
    {
        $table = $this->getRelationValue('table');

        if (! $table) {
            $table = $this->table()->withTrashed()->first();
        }

        if (! $table) {
            return null;
        }

        $schema = $table->getRelationValue('schema');

        if (! $schema) {
            $schema = $table->schema()->withTrashed()->first();
        }

        $schemaName = $schema?->schema_name;

        return $schemaName
            ? $schemaName . '.' . $table->table_name
            : $table->table_name;
    }
}
