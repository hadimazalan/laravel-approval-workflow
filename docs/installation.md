# Installation & Configuration

## Requirements

- PHP 8.2 or higher
- Laravel 10.x, 11.x, or 12.x
- A database supported by Laravel's schema builder (MySQL, PostgreSQL, SQLite, etc.)

## Install

```bash
composer require hadimazalan/laravel-approval-workflow
```

Laravel auto-discovers the service provider `Hadimazalan\ApprovalWorkflow\ApprovalWorkflowServiceProvider` and registers the `Approval` facade alias.

## Publish assets

Publish the configuration file:

```bash
php artisan vendor:publish --tag=approval-workflow-config
```

Publish and run the migration:

```bash
php artisan vendor:publish --tag=approval-workflow-migrations
php artisan migrate
```

This creates four tables:

| Table | Purpose |
|---|---|
| `approval_instances` | One row per workflow instance (polymorphically linked to your model) |
| `approval_steps` | One row per approval level per instance |
| `approval_actions` | Immutable audit trail of every action taken |
| `approval_delegations` | Records of delegated authority |

## Configuration

The published config file is `config/approval-workflow.php`.

### Table names

```php
'tables' => [
    'instances'   => 'approval_instances',
    'steps'       => 'approval_steps',
    'actions'     => 'approval_actions',
    'delegations' => 'approval_delegations',
],
```

Change these if you need to integrate with an existing schema or avoid collisions.

### Database connection

```php
'connection' => null, // uses the default connection
```

Set to a named connection (e.g., `'workflow'`) to isolate workflow tables on a separate database.

### Approver model

```php
'approver_model' => null,
```

**You must set this** when using the built-in `ConfiguredApproverResolver`. Point it at your user model:

```php
'approver_model' => App\Models\User::class,
```

If you implement a custom `ApproverResolver`, you can ignore this setting entirely.

### Approver resolver

```php
'resolver' => Hadimazalan\ApprovalWorkflow\Resolvers\ConfiguredApproverResolver::class,
```

Swap this for your own implementation of `Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver`.

### Notification channels

```php
'channels' => [
    'email' => [
        'driver' => Hadimazalan\ApprovalWorkflow\Notifications\MailNotificationChannel::class,
    ],
    'callback' => [
        'driver' => Hadimazalan\ApprovalWorkflow\Notifications\CallbackNotificationChannel::class,
    ],
],
```

Each key is the name you pass to `->notifyBy(['email', 'whatsapp'])`. The `'driver'` class must implement `NotificationChannel`.

The config also includes `channel_aliases` so friendly names like `'whatsapp'`, `'sms'`, and `'push'` map to the `'callback'` driver:

```php
'channel_aliases' => [
    'whatsapp' => 'callback',
    'sms'      => 'callback',
    'push'     => 'callback',
],
```

### OTP provider

```php
'otp' => [
    'provider' => Hadimazalan\ApprovalWorkflow\Otp\NullOtpChallengeProvider::class,
    'length'   => 6,
    'ttl'      => 300, // seconds
],
```

By default OTP is disabled. Replace `'provider'` with your own implementation of `OtpChallengeProvider` to enforce OTP on sensitive steps. The `length` and `ttl` values are passed to your provider; the default implementation ignores them.

### Audit logger

```php
'audit' => [
    'logger' => Hadimazalan\ApprovalWorkflow\Audit\DatabaseAuditLogger::class,
],
```

Replace with your own implementation of `AuditLogger` to write audit events to Elasticsearch, a message queue, an external SIEM, etc.

### SLA defaults

```php
'sla' => [
    'default_hours' => 48,
    'timezone'      => 'UTC',
],
```

The global default SLA window for each step. May be overridden per level in the fluent builder.

## Getting started

After installation and configuration, define the `approvalInstance` morph-`One` relationship on the model you want to make approval-aware:

```php
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;

class Claim extends Model
{
    public function approvalInstance()
    {
        return $this->morphOne(ApprovalInstance::class, 'approvable');
    }
}
```

Then start a workflow:

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

$instance = Approval::for($claim)
    ->name('Travel claim approval')
    ->level('Head of Department')
    ->level('Finance')
    ->level('CEO')
    ->notifyBy(['email', 'whatsapp'])
    ->start();
```

See the [Fluent API reference](fluent-api.md) for all available builder options.