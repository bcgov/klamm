<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICMSystemMessage extends Model
{
    use HasFactory;

    protected $table = 'icm_system_messages';

    protected $fillable = [
        'view',
        'business_rule',
        'rule_number',
        'message_copy',
        'fix',
        'explanation',

    ];
}
