<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Events\FormVersionUpdateEvent;
use App\Models\FormBuilding\FormScript;
use App\Models\FormBuilding\StyleSheet;
use App\Models\FormBuilding\FormElement;
use App\Models\FormDeployment;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Form;
use App\Models\User;
use App\Models\FormApprovalRequest;
use App\Models\FormMetadata\FormDataSource;

class FormVersion extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'form_id',
        'version_number',
        'status',
        'form_developer_id',
        'footer',
        'comments',
        'components'
    ];

    protected $casts = [];

    protected static $logAttributes = [
        'form_id',
        'version_number',
        'status',
        'form_developer_id',
        'comments',
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
                // FormDataHelper::invalidateFormCache($formVersion->form_id);

                // Determine what fields were updated
                $updateType = 'general';
                $componentsUpdated = false;

                if ($formVersion->isDirty('components') || $formVersion->wasChanged('components')) {
                    $updateType = 'components';
                    $componentsUpdated = true;
                } elseif ($formVersion->isDirty('status') || $formVersion->wasChanged('status')) {
                    $updateType = 'status';
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
                // FormDataHelper::invalidateFormCache($formVersion->form_id);

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
                    return !in_array($change, ['updated_at']);
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

    public static function getStatusColour($status): string
    {
        return match ($status) {
            'Draft' => 'gray',
            'Under Review' => 'warning',
            'Approved' => 'success',
            'Published' => 'primary',
            'Archived' => 'danger',
        };
    }



    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function formDeveloper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'form_developer_id');
    }



    public function webStyleSheet(): HasOne
    {
        return $this->hasOne(StyleSheet::class)->where('type', 'web');
    }

    public function pdfStyleSheet(): HasOne
    {
        return $this->hasOne(StyleSheet::class)->where('type', 'pdf');
    }

    public function webFormScript(): HasOne
    {
        return $this->hasOne(FormScript::class)->where('type', 'web');
    }

    public function pdfFormScript(): HasOne
    {
        return $this->hasOne(FormScript::class)->where('type', 'pdf');
    }

    public function formDataSources(): BelongsToMany
    {
        return $this->belongsToMany(FormDataSource::class, 'form_versions_form_data_sources');
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(FormApprovalRequest::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(FormDeployment::class);
    }

    public function formElements(): HasMany
    {
        return $this->hasMany(FormElement::class)->orderBy('order');
    }
}
