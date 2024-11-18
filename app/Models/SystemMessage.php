<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_type_id',
        'data_group_id',
        'icm_error_code',
        'message_copy',
        'view',
        'fix',
        'explanation',
        'business_rule',
        'rule_number',
        'reference',
    ];

    public function messageType()
    {
        return $this->belongsTo(MessageType::class);
    }

    public function dataGroup()
    {
        return $this->belongsTo(DataGroup::class);
    }

    public function ministries()
    {
        return $this->belongsToMany(Ministry::class, 'system_message_ministry');
    }
}
