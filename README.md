# Laravel Approval Workflow

A reusable, headless Laravel package for multi-level approval workflows. It
ships with a fluent API, database-backed workflow instances, multi-level
approval, delegation, OTP hooks, pluggable notification channels, SLA tracking,
history, and a full audit trail.

- **Namespace:** `Hadimazalan\ApprovalWorkflow`
- **Composer package:** `hadimazalan/laravel-approval-workflow`
- **Laravel:** `^10.0 | ^11.0 | ^12.0`
- **PHP:** `^8.2`

## Why this package?

Approval workflows are everywhere — claims, leave requests, purchase orders,
content publishing, government applications. They share the same shape:

1. A thing is created.
2. It must be approved by N people in order.
3. Some approvals can be delegated.
4. Some approvals require an OTP challenge.
5. Every action must be auditable.

This package provides a clean, headless core for that shape. It is framework
agnostic about **how** you build your UI and **who** your approvers are —
those are pluggable.

## Quick start

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;
use App\Models\Claim;

$claim = Claim::create([...]);

Approval::for($claim)
    ->level('Head of Department')
    ->level('Finance')
    ->level('CEO')
    ->notifyBy(['email', 'whatsapp'])
    ->start();
```

That single call:

- Persists a polymorphic `ApprovalInstance` linked to your `$claim` model.
- Creates one `ApprovalStep` per level, in order.
- Resolves approvers via the configured `ApproverResolver`.
- Dispatches notifications to the first level's approvers via the configured
  channels.

## Acting on a workflow

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

$instance = $claim->approvalInstance;

Approval::approve($instance, $approver, remarks: 'Looks good.');
Approval::reject($instance, $approver, remarks: 'Insufficient evidence.');
Approval::delegate($instance, $fromUser, $toUser, reason: 'On leave.');
```

## Installation

```bash
composer require hadimazalan/laravel-approval-workflow
```

The service provider is auto-discovered.

Publish the config and migration:

```bash
php artisan vendor:publish --tag=approval-workflow-config
php artisan vendor:publish --tag=approval-workflow-migrations
php artisan migrate
```

## Documentation

See [`docs/`](docs/) for full guides:

- [Installation & configuration](docs/installation.md)
- [Fluent API](docs/fluent-api.md)
- [Models & relationships](docs/models.md)
- [Custom approver resolvers](docs/resolvers.md)
- [Notification channels](docs/notifications.md)
- [OTP providers](docs/otp.md)
- [SLA & escalation](docs/sla.md)
- [Audit history](docs/audit.md)
- [Contributing](docs/contributing.md)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
