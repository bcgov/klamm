<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class ErrorActor extends Model
{
    protected $fillable = [
        'name'
    ];

    public function systemMessage(): HasOne
    {
        return $this->hasOne(SystemMessage::class, 'error_actor_id');
    }
}
