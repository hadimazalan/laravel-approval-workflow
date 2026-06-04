<?php

namespace Hadimazalan\ApprovalWorkflow\Notifications;

use Hadimazalan\ApprovalWorkflow\Contracts\NotificationChannel;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Support\Facades\Notification;

class MailNotificationChannel implements NotificationChannel
{
    public function send(object $approver, ApprovalRequestedNotification $notification, ApprovalInstance $instance, ApprovalStep $step): void
    {
        if (! method_exists($approver, 'notify')) {
            return;
        }

        $approver->notify($notification);
    }

    public function name(): string
    {
        return 'email';
    }
}
