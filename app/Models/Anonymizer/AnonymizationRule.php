<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AnonymizationRule extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected $table = 'anonymization_rules';

    protected $fillable = [
        'name',
        'description',
    ];

    protected $appends = ['methods_count', 'columns_count'];

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymization_rules';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->name ?: ('#' . $this->getKey());
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $rule = $this->name ?: ('#' . $this->getKey());

        return match ($eventName) {
            'created' => "Anonymization rule {$rule} created",
            'deleted' => "Anonymization rule {$rule} deleted",
            'restored' => "Anonymization rule {$rule} restored",
            'updated' => "Anonymization rule {$rule} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    // ─── Relationships ──────────────────────────────────────────────

    /**
     * Methods attached to this rule, with strategy and default flag on the pivot.
     */
    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationMethods::class,
            'anonymization_rule_methods',
            'rule_id',
            'method_id'
        )
            ->withPivot(['is_default', 'strategy'])
            ->withTimestamps()
            ->orderByPivot('is_default', 'desc')
            ->orderBy('anonymization_methods.name');
    }

    /**
     * Columns that reference this rule.
     */
    public function columns(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelColumn::class,
            'anonymization_rule_column',
            'rule_id',
            'column_id'
        )->withTimestamps();
    }

    // ─── Accessors ──────────────────────────────────────────────────

    public function getMethodsCountAttribute(): int
    {
        if ($this->relationLoaded('methods')) {
            return $this->methods->count();
        }

        return $this->methods()->count();
    }

    public function getColumnsCountAttribute(): int
    {
        if ($this->relationLoaded('columns')) {
            return $this->columns->count();
        }

        return $this->columns()->count();
    }

    // ─── Method resolution ──────────────────────────────────────────

    /**
     * Get the default method for this rule.
     */
    public function defaultMethod(): ?AnonymizationMethods
    {
        return $this->methods()
            ->wherePivot('is_default', true)
            ->first();
    }

    /**
     * Resolve which method to use for a given strategy.
     * Falls back to the default method if no match is found.
     */
    public function resolveMethod(?string $strategy = null): ?AnonymizationMethods
    {
        if ($strategy !== null && $strategy !== '') {
            $match = $this->methods()
                ->wherePivot('strategy', $strategy)
                ->first();

            if ($match) {
                return $match;
            }
        }

        return $this->defaultMethod();
    }

    // ─── Strategy catalog ───────────────────────────────────────────

    /**
     * Get all distinct strategy labels defined across all rules.
     * Used to populate the strategy picker on jobs.
     */
    public static function availableStrategies(): array
    {
        return DB::table('anonymization_rule_methods')
            ->whereNotNull('strategy')
            ->where('strategy', '!=', '')
            ->distinct()
            ->orderBy('strategy')
            ->pluck('strategy')
            ->all();
    }

    /**
     * Get strategy labels defined on this specific rule.
     */
    public function strategies(): array
    {
        return $this->methods()
            ->wherePivotNotNull('strategy')
            ->get()
            ->pluck('pivot.strategy')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Summary of this rule's method assignments for display.
     */
    public function methodSummary(): string
    {
        $methods = $this->methods;

        if ($methods->isEmpty()) {
            return 'No methods assigned';
        }

        $default = $methods->firstWhere('pivot.is_default', true);
        $strategies = $methods->where('pivot.is_default', false)
            ->pluck('pivot.strategy')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $parts = [];

        if ($default) {
            $parts[] = 'Default: ' . $default->name;
        }

        if ($strategies->isNotEmpty()) {
            $parts[] = $strategies->count() . ' ' . str('strategy')->plural($strategies->count());
        }

        return implode(' · ', $parts);
    }
}
