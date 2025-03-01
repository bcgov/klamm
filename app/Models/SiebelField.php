<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelField extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'business_component_id',
        'table_id',
        'table_column',
        'multi_value_link',
        'multi_value_link_field',
        'join',
        'join_column',
        'calculated_value',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'business_component_id' => 'integer',
        'table_id' => 'integer',
    ];

    /**
     * Get the business component that owns the field.
     */

    public function businessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    /**
     * Get the table that is related to the field.
     */

    public function table(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }
}
