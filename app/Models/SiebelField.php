<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelField extends Model
{
    use HasFactory;

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
     * Get the table that owns the field.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(SiebelTable::class);
    }

    /**
     * Get the business component that owns the field.
     */
    public function businessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
