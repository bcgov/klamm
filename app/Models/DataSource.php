<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'name',
        'description',
        'documentation',
    ];

    public function formFieldDataBindings(): HasMany
    {
        return $this->hasMany(FormFieldDataBinding::class, 'data_sources_id');
    }
}
