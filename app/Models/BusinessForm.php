<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BusinessForm extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'short_description',
        'long_description',
        'internal_description',
        'ado_identifier',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class);
    }

    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class);
    }

    public function formRepositories(): BelongsToMany
    {
        return $this->belongsToMany(FormRepository::class);
    }

    public function formGroups(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\BusinessFormGroup::class);
    }
}
