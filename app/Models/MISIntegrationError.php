<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MISIntegrationError extends Model
{
    use HasFactory;

    protected $table = 'mis_integration_errors';

    protected $fillable = [
        'data_group_id',
        'view',
        'message_copy',
        'fix',
        'explanation',
    ];

    public function dataGroup()
    {
        return $this->belongsTo(DataGroup::class);
    }
}
