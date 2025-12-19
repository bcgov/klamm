<?php

namespace App\Models\Anonymizer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'status',
        'priority',
        'severity',
        'scope_type',
        'scope_name',
        'impact_summary',
        'diff_payload',
        'upload_id',
        'resolved_at',
        'assignee_id',
    ];

    public function upload()
    {
        return $this->belongsTo(\App\Models\Anonymizer\AnonymousUpload::class, 'upload_id');
    }

    public function assignee()
    {
        return $this->belongsTo(\App\Models\User::class, 'assignee_id');
    }
}
