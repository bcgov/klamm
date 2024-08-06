<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserType extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "description"
    ];

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(Form::class, 'form_user_type');
    }
}
