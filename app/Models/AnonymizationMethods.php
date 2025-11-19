<?php

namespace App\Models;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\AnonymizationJobs;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnonymizationMethods extends Model
{
    use HasFactory;

    /**
     * Automatically load the column usage counts to keep metrics consistent.
     *
     * @var array<int, string>
     */
    protected $withCount = ['columns'];

    /**
     * Expose an easy-to-read usage metric for API/Filament resources.
     *
     * @var array<int, string>
     */
    protected $appends = ['usage_count'];

    /**
     * Hide the raw relation-derived count since we surface usage_count instead.
     *
     * @var array<int, string>
     */
    protected $hidden = ['columns_count'];

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'what_it_does',
        'how_it_works',
        'sql_block',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function columns(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelColumn::class,
            'anonymization_method_column',
            'method_id',
            'column_id'
        )->withTimestamps();
    }

    public function jobs(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationJobs::class,
            'anonymization_job_columns',
            'anonymization_method_id',
            'job_id'
        )->withPivot('column_id')
            ->withTimestamps();
    }

    public function getUsageCountAttribute(): int
    {
        $count = $this->getAttribute('columns_count');

        if ($count !== null) {
            return (int) $count;
        }

        if ($this->relationLoaded('columns')) {
            return $this->columns->count();
        }

        return $this->columns()->count();
    }
}
