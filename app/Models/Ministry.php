<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ministry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'short_name',
        'name',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    public function businessAreas()
    {
        return $this->belongsToMany(BusinessArea::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function icmErrorMessages()
    {
        return $this->belongsToMany(
            ICMErrorMessage::class,
            'icm_error_message_ministry',
            'ministry_id',
            'icm_error_message_id'
        );
    }
}
