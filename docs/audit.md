# Audit History

Every action in a workflow is recorded as an immutable audit entry. The audit trail captures who did what, when, and in what context.

## The contract

```php
namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

interface AuditLogger
{
    /** @param array<string, mixed> $context */
    public function record(
        ApprovalInstance $instance,
        ?ApprovalStep $step,
        ?object $actor,
        ApprovalActionType $type,
        ?string $remarks = null,
        array $context = [],
    ): void;
}
```

Every workflow event calls `record()` with:

| Parameter | Description |
|---|---|
| `$instance` | The workflow instance the action belongs to |
| `$step` | The step (if any) the action relates to |
| `$actor` | The user/system that performed the action (can be null for system events) |
| `$type` | The action type — see [action types](#action-types) below |
| `$remarks` | Optional free-text comment |
| `$context` | Arbitrary key-value data for additional context |

## Built-in logger: `DatabaseAuditLogger`

The default implementation (`Hadimazalan\ApprovalWorkflow\Audit\DatabaseAuditLogger`) writes to the `approval_actions` table.

Each row contains:

| Column | Description |
|---|---|
| `instance_id` | FK to `approval_instances` |
| `step_id` | FK to `approval_steps` (nullable, null on step deletion) |
| `actor_type` | Morphs — class of the actor (`App\Models\User`, etc.) |
| `actor_id` | Morphs — primary key of the actor |
| `type` | Action type string (`'created'`, `'approved'`, etc.) |
| `remarks` | Optional comment |
| `context` | JSON payload with action-specific data |
| `occurred_at` | Timestamp of the action |

## Action types

The `ApprovalActionType` enum defines all recorded actions:

| Action | Triggered when |
|---|---|
| `Created` | A workflow is started |
| `Approved` | A step or the entire workflow is approved |
| `Rejected` | A step or workflow is rejected |
| `Delegated` | Approval authority is delegated to another user |
| `Cancelled` | The workflow is cancelled |
| `Expired` | (Reserved — not automatically applied) |
| `OtpSent` | (Reserved — your OTP provider should record this) |
| `OtpFailed` | An invalid OTP code was submitted |
| `Notified` | An approver was notified via a channel; context includes the channel name and any error |

## Querying audit history

### Via the `ApprovalAction` model

```php
use Hadimazalan\ApprovalWorkflow\Models\ApprovalAction;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;

// All actions for a specific workflow
$actions = ApprovalAction::where('instance_id', $instance->id)
    ->orderBy('occurred_at')
    ->get();

// Only approval actions
$approvals = $instance->actions()
    ->where('type', ApprovalActionType::Approved)
    ->get();

// Actions by a specific user
$userActions = ApprovalAction::where('actor_type', User::class)
    ->where('actor_id', $user->id)
    ->whereIn('type', [ApprovalActionType::Approved, ApprovalActionType::Rejected])
    ->get();
```

### Via the `ApprovalInstance` relationship

```php
$instance = ApprovalInstance::with('actions')->find($id);

foreach ($instance->actions as $action) {
    echo "{$action->occurred_at}: {$action->type->value} by {$action->actor_id}";
    if ($action->remarks) {
        echo " — {$action->remarks}";
    }
}
```

### Timeline example

```php
$actions = $instance->actions()->with('step')->orderBy('occurred_at')->get();

$timeline = $actions->map(fn ($a) => [
    'time'    => $a->occurred_at->toIso8601String(),
    'step'    => $a->step?->name ?? '—',
    'action'  => $a->type->value,
    'actor'   => $a->actor_id,
    'remarks' => $a->remarks,
]);
```

## Writing a custom audit logger

Replace the database logger with your own implementation for Elasticsearch, a message queue, an external SIEM, or any other destination.

### Example: JSON log file

```php
namespace App\Workflow\Audit;

use Hadimazalan\ApprovalWorkflow\Contracts\AuditLogger;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Support\Facades\Log;

class JsonAuditLogger implements AuditLogger
{
    public function record(
        ApprovalInstance $instance,
        ?ApprovalStep $step,
        ?object $actor,
        ApprovalActionType $type,
        ?string $remarks = null,
        array $context = [],
    ): void {
        Log::channel('approval-audit')->info($type->value, [
            'instance_id' => $instance->getKey(),
            'step_id'     => $step?->getKey(),
            'step_name'   => $step?->name,
            'actor_type'  => $actor ? $actor::class : null,
            'actor_id'    => $actor?->getKey(),
            'remarks'     => $remarks,
            'context'     => $context,
        ]);
    }
}
```

### Example: Audit event + listener

```php
namespace App\Workflow\Audit;

use Hadimazalan\ApprovalWorkflow\Contracts\AuditLogger;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

class EventAuditLogger implements AuditLogger
{
    public function record(
        ApprovalInstance $instance,
        ?ApprovalStep $step,
        ?object $actor,
        ApprovalActionType $type,
        ?string $remarks = null,
        array $context = [],
    ): void {
        event(new ApprovalActionRecorded(
            instance: $instance,
            step: $step,
            actor: $actor,
            type: $type,
            remarks: $remarks,
            context: $context,
        ));
    }
}
```

Register in `config/approval-workflow.php`:

```php
'audit' => [
    'logger' => App\Workflow\Audit\JsonAuditLogger::class,
],
```

## Testing audit records

```php
use Hadimazalan\ApprovalWorkflow\Models\ApprovalAction;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;

// Assert an approval action was recorded
$this->assertDatabaseHas('approval_actions', [
    'instance_id' => $instance->id,
    'type'        => ApprovalActionType::Approved->value,
]);

// Or via the relationship
$this->assertCount(1, $instance->actions()->where('type', 'approved')->get());
```