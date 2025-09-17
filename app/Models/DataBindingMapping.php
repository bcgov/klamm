<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataBindingMapping extends Model
{
    protected $table = 'data_binding_mappings';

    protected $fillable = [
        'label',
        'description',
        'data_source',
        'endpoint',
        'path_label',
        'data_path',
        'repeating_path',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    // Compose JSONPath from source + label
    public static function composePath(string $source, string $label): string
    {
        $source = trim($source);
        $label  = trim($label);

        if ($source === '' || $label === '') {
            return '';
        }

        return "$.['{$source}'].['{$label}']";
    }

    // Ensure data_path is always consistent before save
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            $model->data_path = self::composePath(
                (string) $model->data_source,
                (string) $model->path_label
            );
        });
    }

    /**
     * Suggest distinct path labels for a given data source and search term.
     * Uses LOWER(..) LIKE for portability & to let PostgreSQL use an index on LOWER(path_label).
     */
    public function scopeSuggestPathLabels(Builder $q, ?string $source, string $term): Builder
    {
        $term = trim($term);

        if ($source !== null && $source !== '') {
            $q->where('data_source', $source);
        }

        if ($term !== '') {
            $q->whereRaw('LOWER(path_label) LIKE ?', ['%' . mb_strtolower($term, 'UTF-8') . '%']);
        }

        return $q->selectRaw('path_label, COUNT(*) as uses')
                 ->groupBy('path_label')
                 ->orderByDesc('uses')
                 ->orderBy('path_label');
    }
}
