<?php

namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Notifications\ApprovalRequestedNotification;

/**
 * Delivers an approval notification to a single approver via a specific
 * transport (email, WhatsApp, SMS, push, etc.).
 */
interface NotificationChannel
{
    /**
     * Send the given notification to the given approver.
     *
     * @param  object  $approver  The approver model resolved by ApproverResolver.
     */
    public function send(object $approver, ApprovalRequestedNotification $notification, ApprovalInstance $instance, ApprovalStep $step): void;

    /**
     * The unique name of this channel. Must match the key in
     * config('approval-workflow.channels').
     */
    public function name(): string;
}
