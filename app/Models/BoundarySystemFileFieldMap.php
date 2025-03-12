<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoundarySystemFileFieldMap extends Model
{
    protected $fillable = [
        'boundary_system_file_id',
        'boundary_system_file_field_id',
        'boundary_system_file_field_map_sections_id',
        'file_structure',
        'mandatory',
    ];

    public function boundarySystemFile(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFile::class, 'boundary_system_file_id');
    }

    public function boundarySystemFileField(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFileField::class, 'boundary_system_file_field_id');
    }

    public function boundarySystemFileFieldMapSection(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemFileFieldMapSection::class, 'boundary_system_file_field_map_sections_id');
    }
}
