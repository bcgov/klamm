<?php

namespace App\Models\FormBuilding;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
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
use App\Models\FormBuilding\FormVersionFormDataSource;
use App\Models\FormBuilding\FormVersionFormInterface;
use Spatie\Activitylog\Models\Activity;
use App\Models\FormMetadata\FormInterface;
use App\Models\SecurityClassification;

class FormVersion extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'form_id',
        'version_number',
        'version_date',
        'version_date_format',
        'security_classification_id',
        'status',
        'form_developer_id',
        'comments',
        'components',
        'pdf_template_name',
        'pdf_template_version',
        'pdf_template_parameters',
        'uses_pets_template',
        'barcode'
    ];

    protected $casts = [
        'uses_pets_template' => 'boolean',
    ];

    protected static $logAttributes = [
        'form_id',
        'version_number',
        'status',
        'form_developer_id',
        'comments',
        'uses_pets_template',
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

    // Return activities for both FormVersions and FormApprovalRequests related to those versions
    public function getAllActivities()
    {
        // Get all related IDs
        $formVersionId = $this->id;
        $formElementIds = $this->formElements()->pluck('id');
        $approvalRequestIds = $this->approvalRequests()->pluck('id');

        return Activity::query()
            ->where(function ($query) use ($formVersionId, $formElementIds, $approvalRequestIds) {
                $query
                    // Activities for this FormVersion
                    // ->orWhere(function ($subQuery) use ($formVersionId) {
                    //     $subQuery->where('subject_type', FormVersion::class)
                    //         ->where('subject_id', $formVersionId);
                    // })
                    // Activities for FormElements in this FormVersion
                    ->orWhere(function ($subQuery) use ($formElementIds) {
                        $subQuery->where('subject_type', \App\Models\FormBuilding\FormElement::class)
                            ->whereIn('subject_id', $formElementIds);
                    });
                // Activities for ApprovalRequests in this FormVersion
                // ->orWhere(function ($subQuery) use ($approvalRequestIds) {
                //     $subQuery->where('subject_type', \App\Models\FormApprovalRequest::class)
                //         ->whereIn('subject_id', $approvalRequestIds);
                // });
            })
            ->orderBy('created_at', 'desc');
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

    public function securityClassification(): BelongsTo
    {
        return $this->belongsTo(SecurityClassification::class);
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
        return $this->belongsToMany(FormDataSource::class, 'form_versions_form_data_sources')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('form_versions_form_data_sources.order');
    }

    public function formVersionFormDataSources(): HasMany
    {
        return $this->hasMany(FormVersionFormDataSource::class)->orderBy('order');
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

    public function formVersionFormInterfaces(): HasMany
    {
        return $this->hasMany(FormVersionFormInterface::class)->orderBy('order');
    }

    public function formInterfaces(): BelongsToMany
    {
        return $this->belongsToMany(FormInterface::class, 'form_version_form_interfaces')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('form_version_form_interfaces.order');
    }

    // Added: many-to-many attachments
    public function styleSheets(): BelongsToMany
    {
        return $this->belongsToMany(StyleSheet::class, 'style_sheet_form_version')
            ->withTimestamps();
    }

    public function formScripts(): BelongsToMany
    {
        return $this->belongsToMany(FormScript::class, 'form_script_form_version')
            ->withTimestamps();
    }

    // Convenience: return all attached style sheets
    public function allStyleSheets()
    {
        return $this->styleSheets()->get();
    }
}
