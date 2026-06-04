<?php

namespace Hadimazalan\ApprovalWorkflow\Notifications;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The canonical notification object passed to all NotificationChannel
 * implementations. Each channel may render this into its own transport
 * (email body, WhatsApp template, etc.).
 */
class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ApprovalInstance $instance,
        public ApprovalStep $step,
        public ?string $remarks = null,
    ) {
    }

    /**
     * Channels the Laravel notification system would use. Channels defined
     * in config('approval-workflow.channels') are dispatched via the
     * NotificationChannel contract directly, so this returns an empty
     * array by default. Mail is the only built-in path that uses the
     * Laravel mailer natively.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $instance = $this->instance;
        $step = $this->step;
        $approvable = $instance->approvable;
        $name = $notifiable->name ?? '';
        $approvableName = $approvable?->name ?? '#' . $instance->approvable_id;

        return (new MailMessage)
            ->subject("Approval requested: {$instance->name}")
            ->greeting("Hello {$name}")
            ->line("A new approval is awaiting your decision.")
            ->line("Workflow: {$instance->name}")
            ->line("Level: {$step->name} (#{$step->level})")
            ->line("Subject: {$approvableName}");
    }
}
