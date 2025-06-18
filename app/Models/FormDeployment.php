<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormDeployment extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'environment',
        'deployed_at',
    ];

    protected $casts = [
        'deployed_at' => 'timestamp',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }
}
