<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnonymizationPackage extends Model
{
    use HasFactory;

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
    ];

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
}
