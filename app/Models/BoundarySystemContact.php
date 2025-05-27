<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoundarySystemContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'organization',
        'notes',
    ];

    protected $appends = ['emails_list'];

    public function boundarySystems(): HasMany
    {
        return $this->hasMany(BoundarySystem::class, 'contact_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(BoundarySystemContactEmail::class, 'contact_id');
    }

    public function getEmailsListAttribute(): string
    {
        if (!$this->relationLoaded('emails')) {
            $this->load('emails');
        }
        return $this->emails->pluck('email')->join(', ');
    }
}
