<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Style extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'declaration'
    ];

    public function styleInstances(): HasMany
    {
        return $this->hasMany(StyleInstance::class);
    }

    public function formFields(): BelongsToMany
    {
        return $this->belongsToMany(FormField::class, 'form_field_style', 'style_id', 'form_field_id')
            ->withTimestamps();
    }
}
