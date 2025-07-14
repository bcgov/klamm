<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FormApprovalRequest;
use App\Models\User;
use App\Notifications\ApprovalRequestReminderNotification;
use Illuminate\Support\Facades\Notification;

class SendEmailReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-email-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails to approvers for pending approval requests every 2 weeks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get pending requests that are at least 2 weeks old
        // and have not been updated in the last 2 weeks
        $approval_requests = FormApprovalRequest::with([
            'formVersion.form',
            'requester',
            'approver'
        ])
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subWeeks(2))
            ->where('updated_at', '<=', now()->subWeeks(2))
            ->get();

        if ($approval_requests->isEmpty()) {
            $this->info('No pending approval requests requiring reminders found.');
            return;
        }

        $reminderCount = 0;
        $userList = [];

        foreach ($approval_requests as $request) {
            try {
                if ($request->approver_id) {
                    // Internal approver
                    $approver = User::find($request->approver_id);
                    if ($approver) {
                        $approver->notify(new ApprovalRequestReminderNotification($request));
                        $this->info("Sent reminder to internal approver: {$approver->name} ({$approver->email})");
                        $userList[] = $approver->email;
                    }
                } else {
                    // External approver
                    Notification::route('mail', $request->approver_email)
                        ->notify(new ApprovalRequestReminderNotification($request));
                    $this->info("Sent reminder to external approver: {$request->approver_name} ({$request->approver_email})");
                    $userList[] = $request->approver_email;
                }

                // Update timestamp to track when reminder was sent
                $request->touch();
                $reminderCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for approval request ID {$request->id}: " . $e->getMessage());
            }
        }

        if ($reminderCount > 0) {
            $this->info("Successfully sent {$reminderCount} reminder emails.");

            $this->info('Emails sent to: ' . implode(', ', $userList));
            // Log the emails sent
        } else {
            $this->info('No reminders were sent.');
        }
    }
}
