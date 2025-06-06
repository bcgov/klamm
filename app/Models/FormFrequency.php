<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormFrequency extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description"
    ];

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }
}
