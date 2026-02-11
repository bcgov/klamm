<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnonymizationColumnTag extends Model
{
    use HasFactory;

    protected $table = 'anonymization_column_tags';

    protected $fillable = [
        'name',
        'category',
        'description',
    ];

    public function columns(): BelongsToMany
    {
        return $this->belongsToMany(
            AnonymousSiebelColumn::class,
            'anonymization_column_tag_column',
            'tag_id',
            'column_id'
        )->withTimestamps();
    }

    public function label(): string
    {
        $category = is_string($this->category) ? trim($this->category) : '';

        return $category !== ''
            ? ($category . ': ' . $this->name)
            : (string) $this->name;
    }
}
