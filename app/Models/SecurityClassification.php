<?php

namespace App\Models;

use App\Models\FormBuilding\FormVersion;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function formVersions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }
}
