<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class FieldGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'label',
        'description',
        'internal_description',
        'repeater',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'repeater' => 'boolean',
    ];

    public function formFields(): BelongsToMany
    {
        return $this->belongsToMany(FormField::class)->withTimestamps();
    }

    public function formVersions(): HasManyThrough
    {
        return $this->hasManyThrough(
            FormVersion::class,
            FieldGroupInstance::class,
            'field_group_id',
            'id',
            'id',
            'form_version_id'
        )->distinct();
    }
}
