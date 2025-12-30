<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\SoftDeletes;


class AnonymousSiebelStaging extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;
    protected $table = 'anonymous_siebel_stagings';

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymous_siebel_stagings';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        $table = $this->table_name ?: null;
        $column = $this->column_name ?: null;

        if ($table && $column) {
            return $table . '.' . $column;
        }

        return $column ?: ($table ?: ('#' . $this->getKey()));
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $subject = $this->activityLogSubjectIdentifier() ?: ('#' . $this->getKey());
        $label = "Staging {$subject}";

        return match ($eventName) {
            'created' => "{$label} created",
            'deleted' => "{$label} deleted",
            'restored' => "{$label} restored",
            'updated' => "{$label} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }
    protected $fillable = [
        'upload_id',
        'database_name',
        'schema_name',
        'object_type',
        'table_name',
        'column_name',
        'column_id',
        'data_type',
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
    ];
    protected $casts = [
        'upload_id' => 'integer',
        'column_id' => 'integer',
        'data_length' => 'integer',
        'data_precision' => 'integer',
        'data_scale' => 'integer',
        'char_length' => 'integer',
        'related_columns' => 'array',
    ];

    public function upload()
    {
        return $this->belongsTo(AnonymousUpload::class, 'upload_id')->withTrashed();
    }
}
