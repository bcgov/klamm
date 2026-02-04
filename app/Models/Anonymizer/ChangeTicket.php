<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeTicket extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected static function activityLogNameOverride(): ?string
    {
        return 'change_tickets';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->title ?: ('#' . $this->getKey());
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $ticket = $this->title ?: ('#' . $this->getKey());

        return match ($eventName) {
            'created' => "Change ticket {$ticket} created",
            'deleted' => "Change ticket {$ticket} deleted",
            'restored' => "Change ticket {$ticket} restored",
            'updated' => "Change ticket {$ticket} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    protected $fillable = [
        'title',
        'status',
        'priority',
        'severity',
        'scope_type',
        'scope_name',
        'impact_summary',
        'diff_payload',
        'upload_id',
        'resolved_at',
        'assignee_id',
    ];

    public function upload()
    {
        return $this->belongsTo(\App\Models\Anonymizer\AnonymousUpload::class, 'upload_id')->withTrashed();
    }

    public function assignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'assignee_id');
    }
}
