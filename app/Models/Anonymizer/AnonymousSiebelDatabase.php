<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelDatabase extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymous_siebel_databases';

    protected $fillable = [
        'database_name',
        'description',
        'content_hash',
        'last_synced_at',
        'changed_at',
        'changed_fields',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'changed_at' => 'datetime',
        'changed_fields' => 'array',
    ];

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymous_siebel_databases';
    }

    protected static function activityLogAttributesOverride(): ?array
    {
        return [
            'database_name',
            'description',
            'content_hash',
            'last_synced_at',
            'changed_at',
        ];
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->database_name;
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $database = $this->database_name ?? ('#' . $this->getKey());

        return match ($eventName) {
            'created' => "Database {$database} created",
            'deleted' => "Database {$database} deleted",
            'restored' => "Database {$database} restored",
            'updated' => "Database {$database} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    public function schemas()
    {
        return $this->hasMany(AnonymousSiebelSchema::class, 'database_id')->withTrashed();
    }
}
