<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'form_version_id',
        'order',
        'instance_id',
        'custom_instance_id',
        'visibility'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'order' => 'integer',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function fieldGroupInstances(): HasMany
    {
        return $this->hasMany(FieldGroupInstance::class);
    }

    public function formInstanceFields(): HasMany
    {
        return $this->hasMany(FormInstanceField::class);
    }

    public function styleInstances(): HasMany
    {
        return $this->hasMany(StyleInstance::class);
    }
}
