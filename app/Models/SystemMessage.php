<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SystemMessage extends Model
{
    protected $fillable = [
        'error_code',
        'error_message',
        'icm_error_solution',
        'explanation' . 'fix',
        'service_desk',
        'limited_data',
    ];

    public function errorEntity(): HasOne
    {
        return $this->hasOne(ErrorEntity::class);
    }

    public function errorDataGroup(): HasOne
    {
        return $this->hasOne(ErrorDataGroup::class);
    }

    public function errorIntegrationState(): HasOne
    {
        return $this->hasOne(ErrorIntegrationState::class);
    }

    public function errorActor(): HasOne
    {
        return $this->hasOne(ErrorActor::class);
    }

    public function errorSource(): HasOne
    {
        return $this->hasOne(ErrorSource::class);
    }
}
