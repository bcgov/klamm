<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoundarySystemFile extends Model
{
    protected $fillable = [
        'interface_id',
        'file_name',
        'file_description',
        'separator',
        'row_separator',
        'comments',
    ];

    public function boundarySystem(): BelongsTo
    {
        return $this->belongsTo(BoundarySystem::class);
    }

    public function separator()
    {
        return $this->belongsTo(BoundarySystemFileSeparator::class, 'boundary_system_file_separator_id');
    }

    public function rowSeparator()
    {
        return $this->belongsTo(BoundarySystemFileSeparator::class, 'boundary_system_file_row_separator_id');
    }
}
