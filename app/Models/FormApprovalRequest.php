<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class FormApprovalRequest extends Model
{
    use HasFactory, LogsActivity;

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

    protected static $logAttributes = [
        'status',
        'approver_name',
        'approver_email',
        'webform_approval',
        'pdf_approval',
        'approver_note',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(self::$logAttributes)
            ->dontSubmitEmptyLogs()
            ->logOnlyDirty()
            ->setDescriptionForEvent(function (string $eventName) {
                $formVersion = $this->formVersion;
                $formName = $formVersion->form->form_title ?? 'Unknown Form';
                $versionNumber = $formVersion->version_number ?? 'Unknown Version';
                $requesterName = $this->requester->name ?? 'Unknown User';

                $approvalTypes = [];
                if ($this->webform_approval) $approvalTypes[] = 'webform';
                if ($this->pdf_approval) $approvalTypes[] = 'PDF';
                $approvalTypesStr = implode(' and ', $approvalTypes);

                if ($eventName === 'created') {
                    return "Form version {$versionNumber} of form '{$formName}' sent to {$this->approver_name} for {$approvalTypesStr} review by {$requesterName}";
                }

                $changes = array_keys($this->getDirty());

                if (in_array('status', $changes)) {
                    switch ($this->status) {
                        case 'completed':
                            return "Form version {$versionNumber} of form '{$formName}' {$approvalTypesStr} approval completed by {$this->approver_name}";
                        case 'rejected':
                            return "Form version {$versionNumber} of form '{$formName}' {$approvalTypesStr} approval rejected by {$this->approver_name}";
                        case 'cancelled':
                            return "Form version {$versionNumber} of form '{$formName}' {$approvalTypesStr} approval request cancelled";
                        default:
                            return "Form version {$versionNumber} of form '{$formName}' approval request status changed to {$this->status}";
                    }
                }

                if (in_array('approver_name', $changes) || in_array('approver_email', $changes)) {
                    return "Form version {$versionNumber} of form '{$formName}' approval request reassigned to {$this->approver_name}";
                }

                return "Form version {$versionNumber} of form '{$formName}' approval request updated";
            });
    }

    public function getLogNameToUse(): string
    {
        return 'form_approval_requests';
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class);
    }

    public function form(): HasOneThrough
    {
        return $this->hasOneThrough(
            Form::class,
            FormVersion::class,
            'id',
            'id',
            'form_version_id',
            'form_id'
        );
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
