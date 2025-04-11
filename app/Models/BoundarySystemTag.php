<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class BoundarySystemTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function interfaces(): BelongsToMany
    {
        return $this->belongsToMany(
            BoundarySystemInterface::class,
            'boundary_system_interface_tag',
            'boundary_system_tag_id',
            'boundary_system_interface_id'
        );
    }
}
