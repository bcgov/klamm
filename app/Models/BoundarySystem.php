<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoundarySystem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'contact_id',
        'is_external',
    ];

    protected $casts = [
        'is_external' => 'boolean',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemContact::class, 'contact_id');
    }

    public function sourceInterfaces(): HasMany
    {
        return $this->hasMany(BoundarySystemInterface::class, 'source_system_id');
    }

    public function targetInterfaces(): HasMany
    {
        return $this->hasMany(BoundarySystemInterface::class, 'target_system_id');
    }
}
