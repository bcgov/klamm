<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoundarySystemContactEmail extends Model
{
    use HasFactory;

    protected $fillable = ['contact_id', 'email'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(BoundarySystemContact::class, 'contact_id');
    }
}
