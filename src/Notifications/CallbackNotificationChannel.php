<?php

namespace Hadimazalan\ApprovalWorkflow\Notifications;

use Hadimazalan\ApprovalWorkflow\Contracts\NotificationChannel;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

/**
 * A pluggable channel that hands the notification off to a closure or
 * external service. Bind an instance to override the default and supply
 * the real transport (WhatsApp Business API, Twilio, Firebase, etc.).
 *
 * Example:
 *
 *   $this->app->bind(
 *       Hadimazalan\ApprovalWorkflow\Notifications\CallbackNotificationChannel::class,
 *       fn () => new CallbackNotificationChannel(function ($approver, $notification) {
 *           // call your WhatsApp gateway
 *       })
 *   );
 */
class CallbackNotificationChannel implements NotificationChannel
{
    /** @var \Closure(object, ApprovalRequestedNotification, ApprovalInstance, ApprovalStep): void */
    protected \Closure $callback;

    public function __construct(?\Closure $callback = null)
    {
        $this->callback = $callback ?? static function (object $approver, ApprovalRequestedNotification $notification, ApprovalInstance $instance, ApprovalStep $step): void {
            // Default no-op: subclasses or container bindings are expected to
            // replace this with a real transport.
        };
    }

    public function send(object $approver, ApprovalRequestedNotification $notification, ApprovalInstance $instance, ApprovalStep $step): void
    {
        ($this->callback)($approver, $notification, $instance, $step);
    }

    public function name(): string
    {
        return 'callback';
    }
}
