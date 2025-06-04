<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\Models\Activity;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'form_title',
        'ministry_id',
        'form_purpose',
        'notes',
        'program',
        'fill_type_id',
        'decommissioned',
        'form_frequency_id',
        'form_reach_id',
        'print_reason',
        'retention_needs',
        'icm_non_interactive',
        'footer_fragment_path',
        'dcv_material_number',
        'orbeon_functions',
        'icm_generated'
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    public function ministry(): BelongsTo
    {
        return $this->belongsTo(Ministry::class);
    }

    public function businessAreas(): BelongsToMany
    {
        return $this->belongsToMany(BusinessArea::class, 'form_business_area');
    }

    public function formTags(): BelongsToMany
    {
        return $this->belongsToMany(FormTag::class, 'form_form_tags');
    }

    public function fillType(): BelongsTo
    {
        return $this->belongsTo(FillType::class);
    }

    public function formLocations(): BelongsToMany
    {
        return $this->belongsToMany(FormLocation::class, 'form_form_location');
    }

    public function formRepositories(): BelongsToMany
    {
        return $this->belongsToMany(FormRepository::class, 'form_repository_form');
    }

    public function formSoftwareSources(): BelongsToMany
    {
        return $this->belongsToMany(FormSoftwareSource::class, 'form_software_source_form');
    }

    public function links(): HasMany
    {
        return $this->hasMany(FormLink::class);
    }

    public function workbenchPaths(): HasMany
    {
        return $this->hasMany(FormWorkbenchPath::class);
    }

    public function userTypes(): BelongsToMany
    {
        return $this->belongsToMany(UserType::class, 'form_user_type');
    }

    public function relatedForms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_related_forms', 'form_id', 'related_form_id');
    }

    public function relatedTo(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_related_forms', 'related_form_id', 'form_id');
    }

    public function formVersions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($form) {
            $relatedFormIds = $form->relatedForms->pluck('id')->toArray();
            $form->relatedForms()->sync($relatedFormIds);
            $form->relatedTo()->sync($relatedFormIds);
        });

        static::deleting(function ($form) {
            $form->relatedTo()->detach();
            $form->relatedForms()->detach();
        });
    }

    public function formFrequency(): BelongsTo
    {
        return $this->belongsTo(FormFrequency::class);
    }

    public function formReach(): BelongsTo
    {
        return $this->belongsTo(FormReach::class);
    }

    public function activities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Activity::class,
            FormVersion::class,
            'form_id',
            'subject_id',
            'id',
            'id'
        )->where('subject_type', FormVersion::class)
            ->select([
                'activity_log.*',
                'form_versions.created_at as version_created_at',
                'form_versions.updated_at as version_updated_at'
            ]);
    }

    public function approvalRequests(): HasManyThrough
    {
        return $this->hasManyThrough(
            FormApprovalRequest::class,
            FormVersion::class,
            'form_id',
            'form_version_id',
            'id',
            'id'
        );
    }
}
