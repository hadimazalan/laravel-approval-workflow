# Fluent API

The package provides two fluent builders: `ApprovalBuilder` (workflow-level) and `ApprovalLevelBuilder` (per-level configuration). Both are accessible through the `Approval` facade.

## Entry point

Every workflow starts with `Approval::for($model)`:

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

Approval::for($claim)
    ->level('Head of Department')
    ->level('Finance')
    ->start();
```

## ApprovalBuilder methods

These methods are called on the builder returned by `Approval::for()`.

### `name(string $name): static`

A human-readable label for the workflow. Stored on the `ApprovalInstance`.

```php
Approval::for($claim)->name('Travel claim approval');
```

### `level(string $name): ApprovalLevelBuilder`

Add an approval level. Returns a per-level builder so you can configure approvers, SLA, OTP, etc. for this specific level.

```php
Approval::for($claim)
    ->level('Head of Department')   // returns ApprovalLevelBuilder
    ->byRole('hod')                  // configure this level
    ->level('Finance')               // add another level
    ->start();
```

### `slaHours(int $hours): static`

Global default SLA in hours for every level. Per-level `->slaHours()` overrides this.

```php
Approval::for($claim)
    ->slaHours(48)
    ->level('Head of Department')
    ->level('Finance')
    ->start();
```

### `notifyBy(array $channels): static`

Default notification channels for all levels. Per-level `->channels()` overrides this.

```php
Approval::for($claim)
    ->notifyBy(['email', 'whatsapp'])
    ->level('Head of Department')
    ->level('Finance')
    ->start();
```

### `withMetadata(array $metadata): static`

Free-form metadata stored on the `ApprovalInstance`. Merges with any previously set metadata.

```php
Approval::for($claim)
    ->withMetadata(['department' => 'Engineering', 'priority' => 'high'])
    ->level('Head of Department')
    ->start();
```

### `start(): ApprovalInstance`

Validate the definition and persist the workflow. Throws `InvalidArgumentException` if no levels are defined. Returns the persisted `ApprovalInstance` with its `steps` relation loaded.

```php
$instance = Approval::for($claim)
    ->level('Head of Department')
    ->start();
```

## ApprovalLevelBuilder methods

These methods configure a single level. They return `$this` so you can chain multiple configurations on one level.

### `approvers(array $ids): self`

Explicit list of approver user IDs (or primary keys).

```php
Approval::for($claim)
    ->level('Head of Department')
    ->approvers([1, 2, 3])
    ->start();
```

### `byRole(string $role): self`

Resolve approvers by role name. Uses `whereHas('roles', ...)` on the configured approver model.

```php
Approval::for($claim)
    ->level('Finance')
    ->byRole('finance-manager')
    ->start();
```

### `byDepartment(string $department): self`

Resolve approvers by department name. Uses `whereHas('departments', ...)` on the configured approver model.

```php
Approval::for($claim)
    ->level('Head of Department')
    ->byDepartment('engineering')
    ->start();
```

### `byRoleAndDepartment(string $role, string $department): self`

Intersection of role and department. Only users who have both the role and belong to the department are included.

```php
Approval::for($claim)
    ->level('Regional Manager')
    ->byRoleAndDepartment('manager', 'asia-pacific')
    ->start();
```

### `resolveUsing(callable $callback): self`

A custom closure for resolving approvers. Receives the `ApprovalStep` and must return an array of approver objects.

```php
Approval::for($claim)
    ->level('Custom')
    ->resolveUsing(function (ApprovalStep $step) {
        return User::where('tenant_id', $step->instance->approvable->tenant_id)->get()->all();
    })
    ->start();
```

### `slaHours(int $hours): self`

Override the default SLA hours for this specific level.

```php
Approval::for($claim)
    ->level('CEO')->slaHours(24)
    ->start();
```

### `requireOtp(bool $on = true): self`

Require OTP verification before this level's approvers can act.

```php
Approval::for($claim)
    ->level('Finance')
    ->requireOtp()
    ->start();
```

### `channels(array $channels): self`

Override the default notification channels for this level only.

```php
Approval::for($claim)
    ->level('Head of Department')
    ->channels(['email'])
    ->level('CEO')
    ->channels(['email', 'sms'])
    ->start();
```

### `notifyBy(array $channels): self`

Alias for `channels()`.

### `done(): ApprovalBuilder`

Explicitly return to the parent builder. Generally unnecessary because the following methods are proxied automatically:

- `level(string $name)`
- `notifyByAll(array $channels)`
- `name(string $name)`
- `start()`

```php
Approval::for($claim)
    ->level('A')->approvers([1])->done()  // explicit return
    ->level('B')->approvers([2])           // implicit via proxy
    ->start();
```

### Proxied methods

These methods on `ApprovalLevelBuilder` delegate to `ApprovalBuilder`, so you never need `->done()` for the common case:

| Method | Effect |
|---|---|
| `level(string $name)` | Add another level |
| `notifyByAll(array $channels)` | Set global channels for all levels |
| `name(string $name)` | Set workflow name |
| `start()` | Persist and start the workflow |

## Complete example

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

$instance = Approval::for($claim)
    ->name('Purchase order approval')
    ->notifyBy(['email'])
    ->withMetadata(['priority' => 'high'])
    ->level('Head of Department')
        ->byDepartment('engineering')
        ->slaHours(24)
    ->level('Finance')
        ->approvers([3, 7])
        ->requireOtp()
        ->channels(['email', 'sms'])
    ->level('CEO')
        ->byRole('ceo')
        ->slaHours(48)
    ->start();
```

## Acting on a workflow

After starting, use the facade to approve, reject, delegate, or cancel:

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

// Approve the current step
Approval::approve($instance, $approver, remarks: 'Looks good.');

// Approve with OTP
Approval::approve($instance, $approver, remarks: 'Verified.', otp: '123456');

// Reject — terminates the entire workflow
Approval::reject($instance, $approver, remarks: 'Insufficient evidence.');

// Delegate to another user
Approval::delegate($instance, $fromUser, $toUser, reason: 'On leave', expiresAt: now()->addDays(7));

// Cancel the workflow
Approval::cancel($instance, $actor, remarks: 'No longer needed.');
```