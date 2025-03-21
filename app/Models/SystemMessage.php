<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemMessage extends Model
{
    protected $fillable = [
        'error_code',
        'error_message',
        'icm_error_solution',
        'explanation',
        'fix',
        'service_desk',
        'limited_data',
        'error_actor_id',
        'error_entity_id',
        'error_data_group_id',
        'error_integration_state_id',
        'error_source_id'
    ];

    public function errorEntity(): BelongsTo
    {
        return $this->belongsTo(ErrorEntity::class, 'error_entity_id');
    }

    public function errorDataGroup(): BelongsTo
    {
        return $this->belongsTo(ErrorDataGroup::class, 'error_data_group_id');
    }

    public function errorIntegrationState(): BelongsTo
    {
        return $this->belongsTo(ErrorIntegrationState::class, 'error_integration_state_id');
    }

    public function errorActor(): BelongsTo
    {
        return $this->belongsTo(ErrorActor::class, 'error_actor_id');
    }

    public function errorSource(): BelongsTo
    {
        return $this->belongsTo(ErrorSource::class, 'error_source_id');
    }
}
