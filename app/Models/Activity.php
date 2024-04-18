<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activity extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'summary',
        'description',
        'submitter',
        'ado_item',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'submitter' => 'integer',
    ];

    public function businessForms(): BelongsToMany
    {
        return $this->belongsToMany(BusinessForm::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
