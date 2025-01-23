<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldGroupInstanceConditionals extends Model
{
    protected $fillable = ['form_version_id', 'field_group_id', 'field_group_instance_id', 'type', 'value'];

    public function fieldGroupInstance(): BelongsTo
    {
        return $this->belongsTo(FieldGroupInstance::class);
    }
}
