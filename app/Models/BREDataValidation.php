<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BREDataValidation extends Model
{
    use HasFactory;

    protected $table = 'bre_data_validations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'validation_type_id',
        'validation_criteria',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'validation_type_id' => 'integer',
    ];

    public function breValidationType(): BelongsTo
    {
        return $this->belongsTo(BREValidationType::class, 'validation_type_id');
    }

    public function getBREValidationTypeAttribute()
    {
        return $this->breValidationType()->first()?->name;
    }
}
