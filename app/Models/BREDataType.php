<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BREDataType extends Model
{
    use HasFactory;

    protected $table = 'bre_data_types';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'value_type_id',
        'short_description',
        'long_description',
        'validation',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'value_type_id' => 'integer',
    ];

    public function breValueType(): BelongsTo
    {
        return $this->belongsTo(BREValueType::class, 'value_type_id');
    }

    public function getBREValueTypeNameAttribute()
    {
        return $this->breValueType ? $this->breValueType->name : null;
    }
}
