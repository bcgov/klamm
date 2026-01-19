<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelTable extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymous_siebel_tables';

    protected $fillable = [
        'object_type',
        'table_name',
        'table_comment',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
        'schema_id',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymous_siebel_tables';
    }

    protected static function activityLogAttributesOverride(): ?array
    {
        return [
            'schema_id',
            'object_type',
            'table_name',
            'table_comment',
            'content_hash',
            'last_synced_at',
            'changed_at',
        ];
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->table_name;
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $schema = $this->getRelationValue('schema');

        if (! $schema) {
            $schema = $this->schema()->withTrashed()->first();
        }

        $schemaName = $schema?->schema_name;
        $table = $this->table_name ?? ('#' . $this->getKey());
        $qualified = $schemaName ? $schemaName . '.' . $table : $table;

        return match ($eventName) {
            'created' => "Table {$qualified} created",
            'deleted' => "Table {$qualified} deleted",
            'restored' => "Table {$qualified} restored",
            'updated' => "Table {$qualified} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    public function schema()
    {
        return $this->belongsTo(AnonymousSiebelSchema::class, 'schema_id')->withTrashed();
    }

    public function columns()
    {
        return $this->hasMany(AnonymousSiebelColumn::class, 'table_id')->withTrashed();
    }
}
