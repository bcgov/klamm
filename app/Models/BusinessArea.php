<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessArea extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description",
        "short_name"
    ];

    public function ministries(): BelongsToMany
    {
        return $this->belongsToMany(Ministry::class);
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_business_area');
    }
}
