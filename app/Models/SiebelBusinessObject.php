<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiebelBusinessObject extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'changed',
        'repository_name',
        'inactive',
        'comments',
        'object_locked',
        'object_language_locked',
        'project_id',
        'primary_business_component_id',
        'query_list_business_component_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'changed' => 'boolean',
        'inactive' => 'boolean',
        'object_locked' => 'boolean',
        'project_id' => 'integer',
        'primary_business_component_id' => 'integer',
        'query_list_business_component_id' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SiebelProject::class);
    }

    public function primaryBusinessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }

    public function queryListBusinessComponent(): BelongsTo
    {
        return $this->belongsTo(SiebelBusinessComponent::class);
    }
}
