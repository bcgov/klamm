<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Helpers\FormDataHelper;
use App\Events\FormVersionUpdateEvent;

class FormVersion extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'form_id',
        'version_number',
        'status',
        'form_developer_name',
        'form_developer_email',
        'updater_name',
        'updater_email',
        'footer',
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
        'form_developer_name',
        'form_developer_email',
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

        // After saving a form version, invalidate related caches and dispatch event
        static::saved(function ($formVersion) {
            if ($formVersion->form_id) {
                FormDataHelper::invalidateFormCache($formVersion->form_id);

                // Determine what fields were updated
                $updateType = 'general';
                $componentsUpdated = false;

                if ($formVersion->isDirty('components') || $formVersion->wasChanged('components')) {
                    $updateType = 'components';
                    $componentsUpdated = true;
                } elseif ($formVersion->isDirty('status') || $formVersion->wasChanged('status')) {
                    $updateType = 'status';
                } elseif ($formVersion->isDirty('deployed_to') || $formVersion->isDirty('deployed_at')) {
                    $updateType = 'deployment';
                }

                // Dispatch the update event
                event(new FormVersionUpdateEvent(
                    $formVersion->id,
                    $formVersion->form_id,
                    $formVersion->version_number,
                    $componentsUpdated ? $formVersion->components : null,
                    $updateType
                ));
            }
        });

        // When a form version is deleted
        static::deleted(function ($formVersion) {
            if ($formVersion->form_id) {
                FormDataHelper::invalidateFormCache($formVersion->form_id);

                // Dispatch deletion event
                event(new FormVersionUpdateEvent(
                    $formVersion->id,
                    $formVersion->form_id,
                    $formVersion->version_number,
                    null,
                    'deleted'
                ));
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $formTitle = $this->form ? "'{$this->form->form_title}'" : '';

                if ($eventName === 'created') {
                    return "Form {$formTitle} version {$this->version_number} was created";
                }

                $changes = array_keys($this->getDirty());

                // Filter out unnecessary fields from changes description
                $changes = array_filter($changes, function ($change) {
                    return !in_array($change, ['updated_at', 'updater_name', 'updater_email']);
                });

                if (!empty($changes)) {
                    $changes = array_map(function ($change) {
                        $change = str_replace('_', ' ', $change);
                        $change = str_replace('form developer', 'developer', $change);
                        return $change;
                    }, $changes);

                    $changesStr = implode(', ', array_unique($changes));
                    return "Form {$formTitle} version {$this->version_number} had changes to: {$changesStr}";
                }

                return "Form {$formTitle} version {$this->version_number} was {$eventName}";
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

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(FormApprovalRequest::class);
    }
}
