<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomusR extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'field_name',
        'description',
        'field_type',
        'field_type_length',
        'source',
        'screen',
        'table',
        'condition',
        'table_code',
        'lookup_field',
        'database_name',
        'integration_id',
        'xml_id',
        'lookup_id',
        'have_duplicate',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'integration_id' => 'integer',
        'xml_id' => 'integer',
        'lookup_id' => 'integer',
        'have_duplicate' => 'boolean',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function xml(): BelongsTo
    {
        return $this->belongsTo(Xml::class);
    }

    public function lookup(): BelongsTo
    {
        return $this->belongsTo(Lookup::class);
    }
}
