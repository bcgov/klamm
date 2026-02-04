<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AnonymousSiebelDataType extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymous_siebel_data_types';
    protected $fillable = [
        'data_type_name',
        'description',
    ];

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymous_siebel_data_types';
    }

    protected static function activityLogAttributesOverride(): ?array
    {
        return [
            'data_type_name',
            'description',
        ];
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->data_type_name;
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $type = $this->data_type_name ?? ('#' . $this->getKey());

        return match ($eventName) {
            'created' => "Data type {$type} created",
            'deleted' => "Data type {$type} deleted",
            'restored' => "Data type {$type} restored",
            'updated' => "Data type {$type} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    public function columns()
    {
        return $this->hasMany(AnonymousSiebelColumn::class, 'data_type_id')->withTrashed();
    }
}
