# Notification Channels

When a step is activated, the workflow notifies all resolved approvers via the configured channels. The notification system is pluggable: each channel implements the `NotificationChannel` contract.

## The contract

```php
namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Notifications\ApprovalRequestedNotification;

interface NotificationChannel
{
    public function send(
        object $approver,
        ApprovalRequestedNotification $notification,
        ApprovalInstance $instance,
        ApprovalStep $step,
    ): void;

    public function name(): string;
}
```

- `send()` delivers the notification to a single approver. Called once per approver per channel.
- `name()` returns the channel name, which must match the config key used in `config('approval-workflow.channels')`.

## Built-in channels

### Email channel

`Hadimazalan\ApprovalWorkflow\Notifications\MailNotificationChannel`

Uses Laravel's built-in notification system: calls `$approver->notify($notification)`. The `ApprovalRequestedNotification` renders a `MailMessage` with the workflow name, level, and subject.

Requires that your approver model uses Laravel's `Notifiable` trait and has a `routeNotificationForMail()` method (or `email` attribute).

```php
// config/approval-workflow.php
'channels' => [
    'email' => [
        'driver' => MailNotificationChannel::class,
    ],
],
```

#### Customizing the email template

The `ApprovalRequestedNotification` has a `toMail()` method you can customize. Extend the default notification and bind your version in the container:

```php
namespace App\Notifications;

use Hadimazalan\ApprovalWorkflow\Notifications\ApprovalRequestedNotification;

class CustomApprovalNotification extends ApprovalRequestedNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[Urgent] {$this->instance->name}")
            ->greeting("Hello {$notifiable->name}")
            ->line("You have been assigned as an approver for:")
            ->line("**{$this->instance->name}** — Level {$this->step->level}")
            ->action('Review', url('/approvals/' . $this->instance->id))
            ->line('Please review and take action before the SLA deadline.');
    }
}
```

### Callback channel

`Hadimazalan\ApprovalWorkflow\Notifications\CallbackNotificationChannel`

A generic channel that delegates to a closure. Use this for WhatsApp, SMS, push notifications, or any custom transport.

**Default behaviour:** no-op (the default closure does nothing).

**Override via container binding:**

```php
use Hadimazalan\ApprovalWorkflow\Notifications\CallbackNotificationChannel;
use App\Services\WhatsAppService;

$this->app->bind(CallbackNotificationChannel::class, function () {
    return new CallbackNotificationChannel(function ($approver, $notification, $instance, $step) {
        $whatsapp = app(WhatsAppService::class);
        $whatsapp->send(
            to: $approver->phone,
            template: 'approval_requested',
            data: [
                'name' => $instance->name,
                'level' => $step->name,
                'url' => url('/approvals/' . $instance->id),
            ],
        );
    });
});
```

**Registering the channel in config:**

```php
// config/approval-workflow.php
'channels' => [
    'email' => [ 'driver' => MailNotificationChannel::class ],
    'callback' => [ 'driver' => CallbackNotificationChannel::class ],
],
```

#### Using channel aliases

The config includes aliases so friendly names like `'whatsapp'` map to the `'callback'` driver:

```php
'channel_aliases' => [
    'whatsapp' => 'callback',
    'sms'      => 'callback',
    'push'     => 'callback',
],
```

This lets you write expressive builder calls:

```php
Approval::for($claim)
    ->notifyBy(['email', 'whatsapp', 'sms'])
    ->level('Head of Department')
    ->start();
```

All three names resolve to the same configured channels. If you need separate drivers for each transport, define distinct channel entries in `'channels'` and remove the aliases.

## Writing a custom channel

Implement the `NotificationChannel` interface:

```php
namespace App\Workflow\Channels;

use Hadimazalan\ApprovalWorkflow\Contracts\NotificationChannel;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Notifications\ApprovalRequestedNotification;

class SlackNotificationChannel implements NotificationChannel
{
    public function __construct(protected string $webhookUrl) {}

    public function send(
        object $approver,
        ApprovalRequestedNotification $notification,
        ApprovalInstance $instance,
        ApprovalStep $step,
    ): void {
        $message = "Approval needed: {$instance->name} (Level {$step->level})";

        Http::post($this->webhookUrl, [
            'text' => $message,
            'attachments' => [[
                'text' => "Assigned to: {$approver->name}",
                'actions' => [
                    ['type' => 'button', 'text' => 'Approve', 'url' => url("/approvals/{$instance->id}/approve")],
                    ['type' => 'button', 'text' => 'Reject', 'url' => url("/approvals/{$instance->id}/reject")],
                ],
            ]],
        ]);
    }

    public function name(): string
    {
        return 'slack';
    }
}
```

Register it in `config/approval-workflow.php`:

```php
'channels' => [
    'email' => [ 'driver' => MailNotificationChannel::class ],
    'slack' => [ 'driver' => App\Workflow\Channels\SlackNotificationChannel::class ],
],
```

## Channel resolution order

When a step is activated, the `ApprovalManager` resolves channels in this order:

1. If the level defines channels via `->channels([...])`, those are used.
2. Otherwise, the global `->notifyBy([...])` channels on the builder apply.
3. If neither is set, no notifications are sent (the step still activates, but approvers must be informed by other means).

The channel names are resolved through the alias map, then looked up in the configured `'channels'` registry. Unknown names are silently skipped.

## Notification object

The `ApprovalRequestedNotification` is passed to every channel. It extends Laravel's `Notification` and is `Queueable`, so if you send it directly via Laravel's notification system (outside of the channel contract), it can be queued.

```php
$notification = new ApprovalRequestedNotification($instance, $step, $remarks);

$notification->instance;  // ApprovalInstance
$notification->step;      // ApprovalStep
$notification->remarks;   // optional string
```

## Error handling

If a channel throws an exception during `send()`, the workflow catches it, records an audit action (`ApprovalActionType::Notified` with the error message in context), and continues. A failure in one channel never blocks notification delivery via other channels.

## Per-level channel override

You can specify different channels for different levels:

```php
Approval::for($claim)
    ->level('Head of Department')
        ->channels(['email'])
    ->level('Finance')
        ->channels(['email', 'sms'])
    ->level('CEO')
        ->channels(['email', 'whatsapp'])
    ->start();
```

This requires the channel names to be registered in `config('approval-workflow.channels')`.