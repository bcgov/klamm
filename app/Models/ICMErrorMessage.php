<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ICMErrorMessage extends Model
{
    use HasFactory;

    protected $table = 'icm_error_messages';

    protected $fillable = [
        'icm_error_code',
        'business_rule',
        'rule_number',
        'message_copy',
        'fix',
        'explanation',
        'reference',
    ];

    public function ministries()
    {
        return $this->belongsToMany(
            Ministry::class,
            'icm_error_message_ministry',
            'icm_error_message_id',
            'ministry_id'
        );
    }
}
