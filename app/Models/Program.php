<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Program extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'short_name',
        'description',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function businessForms(): BelongsToMany
    {
        return $this->belongsToMany(BusinessForm::class);
    }

    public function businessFormGroups(): BelongsToMany
    {
        return $this->belongsToMany(BusinessFormGroup::class);
    }
}
