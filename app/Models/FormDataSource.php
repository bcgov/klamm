<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FormDataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'source',
        'description'
    ];

    public function formVersions(): BelongsToMany
    {
        return $this->belongsToMany(FormVersion::class, 'form_versions_form_data_sources');
    }
}
