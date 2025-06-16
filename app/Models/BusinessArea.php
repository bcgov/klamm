<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessArea extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description",
        "short_name"
    ];

    public function ministries(): BelongsToMany
    {
        return $this->belongsToMany(Ministry::class);
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_business_area');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Get total number of forms.
     */
    public function getFormCount($forms = null): int
    {
        $forms = $forms ?? $this->getRelationValue('forms');
        if ($forms === null) {
            throw new \RuntimeException('Forms relation must be eager loaded.');
        }
        return $forms->count();
    }

    /**
     * Count forms with migration2025_status not 'Not Applicable'
     */
    public function countFormsMigration2025($forms = null): int
    {
        $forms = $forms ?? $this->getRelationValue('forms');
        if ($forms === null) {
            throw new \RuntimeException('Forms relation must be eager loaded.');
        }
        return $forms->filter(fn($form) => $form->migration2025_status !== 'Not Applicable')->count();
    }

    /**
     * Count forms with migration2025_status 'Completed'
     */
    public function countFormsCompleted($forms = null): int
    {
        $forms = $forms ?? $this->getRelationValue('forms');
        if ($forms === null) {
            throw new \RuntimeException('Forms relation must be eager loaded.');
        }
        return $forms->filter(fn($form) => $form->migration2025_status === 'Completed')->count();
    }

    /**
     * Count forms with migration2025_status 'In Progress'
     */
    public function countFormsInProgress($forms = null): int
    {
        $forms = $forms ?? $this->getRelationValue('forms');
        if ($forms === null) {
            throw new \RuntimeException('Forms relation must be eager loaded.');
        }
        return $forms->filter(fn($form) => $form->migration2025_status === 'In Progress')->count();
    }

    /**
     * Count forms with migration2025_status 'To Be Done'
     */
    public function countFormsToBeDone($forms = null): int
    {
        $forms = $forms ?? $this->getRelationValue('forms');
        if ($forms === null) {
            throw new \RuntimeException('Forms relation must be eager loaded.');
        }
        return $forms->filter(fn($form) => $form->migration2025_status === 'To Be Done')->count();
    }
}
