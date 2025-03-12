<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BoundarySystem extends Model
{
    protected $fillable = [
        'interface_name',
        'interface_description',
        'source_system',
        'target_system',
        'mode_of_transfer',
        'file_format',
        'boundary_system_frequency_id',
        'date_time',
        'source_point_of_contact',
        'target_point_of_contact',
    ];

    public function boundarySystemFrequency(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFrequency::class);
    }

    public function boundarySystemFileFormat(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFileFormat::class);
    }

    public function boundarySystemModeOfTransfer(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemModeOfTransfer::class);
    }

    public function boundarySystemSystem(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemSystem::class);
    }

    public function sourceSystem()
    {
        return $this->belongsTo(BoundarySystemSystem::class, 'boundary_system_source_system_id');
    }

    public function targetSystem()
    {
        return $this->belongsTo(BoundarySystemSystem::class, 'boundary_system_target_system_id');
    }

    public function boundarySystemProcess(): BelongsToMany
    {
        return $this->belongsToMany(BoundarySystemProcess::class);
    }
}
