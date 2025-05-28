<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_version_id',
        'requester_id',
        'approver_id',
        'approver_name',
        'approver_email',
        'requester_note',
        'approver_note',
        'webform_approval',
        'pdf_approval',
        'is_klamm_user',
        'status',
        'token',
        'approved_at',
        'rejected_at'
    ];

    protected $casts = [
        'webform_approval' => 'boolean',
        'pdf_approval' => 'boolean',
        'is_klamm_user' => 'boolean',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
