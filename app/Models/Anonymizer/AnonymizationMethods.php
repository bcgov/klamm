<?php

namespace App\Models\Anonymizer;

use App\Models\Anonymizer\AnonymousSiebelColumn;
use App\Models\Anonymizer\AnonymizationJobs;
use App\Models\Anonymizer\AnonymizationPackage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnonymizationMethods extends Model
{
    use HasFactory;

    public const CATEGORY_SHUFFLE_MASKING = 'Shuffle Masking';
    public const CATEGORY_BLURRING_PERTURBATION = 'Blurring or Perturbation';
    public const CATEGORY_ENCRYPTION = 'Encryption';
    public const CATEGORY_FORMAT_PRESERVING_RANDOMIZATION = 'Format Preserving Randomization';
    public const CATEGORY_CONDITIONAL_MASKING = 'Conditional Masking';
    public const CATEGORY_COMPOUND_MASKING = 'Compound Masking';
    public const CATEGORY_DETERMINISTIC_MASKING = 'Deterministic Masking';

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
    protected $appends = ['usage_count', 'seed_capability_summary'];

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
        'categories',
        'description',
        'what_it_does',
        'how_it_works',
        'sql_block',
        'emits_seed',
        'requires_seed',
        'supports_composite_seed',
        'seed_notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'integer',
        'categories' => 'array',
        'emits_seed' => 'boolean',
        'requires_seed' => 'boolean',
        'supports_composite_seed' => 'boolean',
    ];

    /**
     * Canonical masking categories used across the method library.
     */
    public static function categoryOptions(): array
    {
        return [
            self::CATEGORY_SHUFFLE_MASKING,
            self::CATEGORY_BLURRING_PERTURBATION,
            self::CATEGORY_ENCRYPTION,
            self::CATEGORY_FORMAT_PRESERVING_RANDOMIZATION,
            self::CATEGORY_CONDITIONAL_MASKING,
            self::CATEGORY_COMPOUND_MASKING,
            self::CATEGORY_DETERMINISTIC_MASKING,
        ];
    }

    /**
     * Merge canonical categories with any existing values in the database.
     *
     * PostgreSQL cannot DISTINCT over JSON columns, so we gather and de-dup in PHP.
     */
    public static function categoryOptionsWithExisting(): array
    {
        $existingLegacy = self::query()
            ->whereNotNull('category')
            ->pluck('category')
            ->filter()
            ->map(fn(string $value) => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existingTagged = self::query()
            ->whereNotNull('categories')
            ->get(['categories'])
            ->pluck('categories')
            ->flatMap(function ($value) {
                if (! is_array($value)) {
                    return [];
                }

                return array_values(array_filter(array_map(function ($item) {
                    return is_string($item) ? trim($item) : null;
                }, $value)));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_values(array_unique(array_merge(self::categoryOptions(), $existingLegacy, $existingTagged)));
    }

    /**
     * Backwards-compatible category label used by older UI blocks.
     */
    public function categorySummary(): ?string
    {
        $categories = $this->getAttribute('categories');

        if (is_array($categories) && $categories !== []) {
            $labels = array_values(array_filter(array_map(fn($v) => is_string($v) ? trim($v) : null, $categories)));

            return $labels !== [] ? implode(' • ', $labels) : null;
        }

        $legacy = $this->getAttribute('category');

        return is_string($legacy) && trim($legacy) !== '' ? trim($legacy) : null;
    }

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

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationPackage::class,
            'anonymization_method_package',
            'anonymization_method_id',
            'anonymization_package_id'
        )->withTimestamps();
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

    public function getSeedCapabilitySummaryAttribute(): string
    {
        $flags = [];

        if ($this->emits_seed) {
            $flags[] = 'Emits seed';
        }

        if ($this->requires_seed) {
            $flags[] = 'Requires seed';
        }

        if ($this->supports_composite_seed) {
            $flags[] = 'Composite-ready';
        }

        return $flags === []
            ? 'No seed contract declared'
            : implode(' • ', $flags);
    }
}
