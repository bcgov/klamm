<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FormVersion extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'form_id',
        'version_number',
        'status',
        'form_requester_name',
        'form_requester_email',
        'form_developer_name',
        'form_developer_email',
        'form_approver_name',
        'form_approver_email',
        'updater_name',
        'updater_email',
        'comments',
        'deployed_to',
        'deployed_at',
        'components'
    ];

    protected $casts = [
        'deployed_at' => 'datetime',
    ];

    protected static $logAttributes = [
        'form_id',
        'version_number',
        'status',
        'form_requester_name',
        'form_requester_email',
        'form_developer_name',
        'form_developer_email',
        'form_approver_name',
        'form_approver_email',
        'comments',
        'deployed_to',
        'deployed_at',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($formVersion) {
            $latestVersion = FormVersion::where('form_id', $formVersion->form_id)
                ->orderBy('version_number', 'desc')
                ->first();

            $formVersion->version_number = $latestVersion ? $latestVersion->version_number + 1 : 1;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                if ($eventName === 'created') {
                    return "Form version {$this->version_number} was created";
                }

                $changes = array_keys($this->getDirty());

                // Filter out unnecessary fields from changes description
                $changes = array_filter($changes, function ($change) {
                    return !in_array($change, ['updated_at', 'updater_name', 'updater_email']);
                });

                if (!empty($changes)) {
                    $changes = array_map(function ($change) {
                        $change = str_replace('_', ' ', $change);
                        $change = str_replace('form requester', 'requester', $change);
                        $change = str_replace('form approver', 'approver', $change);
                        $change = str_replace('form developer', 'developer', $change);
                        return $change;
                    }, $changes);

                    $changesStr = implode(', ', array_unique($changes));
                    return "Form version {$this->version_number} had changes to: {$changesStr}";
                }

                return "Form version {$this->version_number} was {$eventName}";
            });
    }

    public function getLogNameToUse(): string
    {
        return 'form_versions';
    }

    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'under_review' => 'Under Review',
            'approved' => 'Approved',
            'published' => 'Published',
            'archived' => 'Archived',
        ];
    }

    public function getFormattedStatusName(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function formInstanceFields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class);
    }

    public function fieldGroupInstances(): HasMany
    {
        return $this->hasMany(FieldGroupInstance::class);
    }

    public function containers(): HasMany
    {
        return $this->hasMany(Container::class);
    }

    public function formDataSources(): BelongsToMany
    {
        return $this->belongsToMany(FormDataSource::class, 'form_versions_form_data_sources');
    }
}
