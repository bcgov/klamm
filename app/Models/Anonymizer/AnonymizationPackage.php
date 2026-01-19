<?php

namespace App\Models\Anonymizer;

use App\Traits\LogsAnonymizerActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AnonymizationPackage extends Model
{
    use SoftDeletes, HasFactory, LogsAnonymizerActivity;

    protected static function activityLogNameOverride(): ?string
    {
        return 'anonymization_packages';
    }

    protected function activityLogSubjectIdentifier(): ?string
    {
        return $this->handle ?: ($this->name ?: ('#' . $this->getKey()));
    }

    protected function describeActivityEvent(string $eventName, array $context = []): string
    {
        $label = $this->handle ?: ($this->name ?: ('#' . $this->getKey()));

        return match ($eventName) {
            'created' => "Anonymization package {$label} created",
            'deleted' => "Anonymization package {$label} deleted",
            'restored' => "Anonymization package {$label} restored",
            'updated' => "Anonymization package {$label} updated",
            default => $this->defaultActivityDescription($eventName, $context),
        };
    }

    protected $fillable = [
        'name',
        'handle',
        'package_name',
        'database_platform',
        'summary',
        'install_sql',
        'package_spec_sql',
        'package_body_sql',
    ];

    protected $casts = [
        'id' => 'integer',
        'version' => 'integer',
        'version_root_id' => 'integer',
        'supersedes_id' => 'integer',
        'is_current' => 'boolean',
    ];

    public function versionRoot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'version_root_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'version_root_id')->orderBy('version');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function methods(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymizationMethods::class,
            'anonymization_method_package',
            'anonymization_package_id',
            'anonymization_method_id'
        )->withTimestamps();
    }

    public function getDisplayLabelAttribute(): string
    {
        $platform = $this->database_platform ? strtoupper($this->database_platform) : null;
        $package = $this->package_name ?: null;
        $segments = array_filter([$this->name, $package, $platform]);

        return $segments !== [] ? implode(' • ', $segments) : $this->name;
    }

    public function compiledSqlBlocks(): array
    {
        return array_filter([
            trim((string) $this->install_sql),
            trim((string) $this->package_spec_sql),
            trim((string) $this->package_body_sql),
        ]);
    }

    public function isInUse(): bool
    {
        // Packages are "in use" if any attached methods are referenced by jobs or columns.
        return $this->methods()
            ->where(function ($query) {
                $query
                    ->whereHas('columns')
                    ->orWhereHas('jobs');
            })
            ->exists();
    }

    public function createNewVersion(): self
    {
        // Create a new version record while preserving the old one.
        // No automatic copying of methods or other relationships.
        return DB::transaction(function () {
            $rootId = $this->version_root_id ?: $this->getKey();

            $nextVersion = (int) self::query()
                ->where('version_root_id', $rootId)
                ->max('version');
            $nextVersion = max(1, $nextVersion) + 1;

            $this->forceFill([
                'is_current' => false,
                'version_root_id' => $rootId,
            ])->save();

            $new = $this->replicate([
                'methods_count',
            ]);
            $new->version_root_id = $rootId;
            $new->supersedes_id = $this->getKey();
            $new->version = $nextVersion;
            $new->is_current = true;

            // versioning handle
            $baseHandle = (string) $this->handle;
            $new->handle = mb_strimwidth($baseHandle . '-v' . $nextVersion, 0, 255, '');

            // versioning
            $baseName = (string) $this->name;
            $new->name = mb_strimwidth($baseName . ' (v' . $nextVersion . ')', 0, 255, '');

            $new->save();

            return $new;
        });
    }
}
