# SLA & Escalation

The package tracks service-level agreement (SLA) deadlines at both the instance and step level. SLA enforcement, reminders, and escalation logic are left to your application — the package provides the data points you need.

## How SLA is computed

### Global default

Configured in `config/approval-workflow.php`:

```php
'sla' => [
    'default_hours' => 48,
    'timezone'      => 'UTC',
],
```

This is used as the default per-step SLA hours when no level-specific value is provided.

### Per-level override

```php
Approval::for($claim)
    ->level('Head of Department')->slaHours(24)
    ->level('Finance')->slaHours(48)
    ->level('CEO')->slaHours(72)
    ->start();
```

Each level can have its own SLA window. The per-level value takes precedence over the global default.

### Instance-level deadline

The instance's `sla_due_at` column stores the **sum** of all per-step SLA hours, computed from `started_at`:

```
instance.sla_due_at = started_at + (level1.sla_hours + level2.sla_hours + ...)
```

This represents the total time budget for the entire workflow. The timezone from config is used for the computation.

### Per-step deadline

When a step is activated, its `sla_due_at` is computed:

```
step.sla_due_at = activated_at + step.sla_hours
```

This gives you a per-step deadline independent of the overall workflow deadline.

## Querying overdue workflows

The `ApprovalInstance` model provides scopes and methods for SLA monitoring.

### Overdue instances

```php
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;

// Get all pending workflows past their overall SLA deadline
$overdue = ApprovalInstance::overdue()->get();

// Check a single instance
if ($instance->isOverdue()) {
    // Trigger escalation
}
```

The `overdue` scope filters to pending instances where `sla_due_at` is not null and is in the past.

### Overdue steps

```php
$step->isOverdue(); // bool
```

Returns `true` if the step is currently active and its per-step SLA deadline has passed.

## Building an escalation scheduler

The package does not include a built-in scheduler for escalation. You implement one in your application using Laravel's scheduler.

### Example: daily escalation check

Create a command:

```php
namespace App\Console\Commands;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Illuminate\Console\Command;

class EscalateOverdueApprovals extends Command
{
    protected $signature = 'approvals:escalate';
    protected $description = 'Escalate overdue approval workflows';

    public function handle(): int
    {
        $overdue = ApprovalInstance::overdue()->with('steps', 'approvable')->get();

        foreach ($overdue as $instance) {
            $currentStep = $instance->currentStep();

            // Notify the current approver's manager, or re-assign, etc.
            event(new ApprovalOverdue($instance, $currentStep));
        }

        $this->info("Escalated {$overdue->count()} overdue workflows.");

        return Command::SUCCESS;
    }
}
```

Register it in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('approvals:escalate')->everyFourHours();
}
```

### Example: SLA reminder notifications

```php
namespace App\Console\Commands;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Illuminate\Console\Command;

class SendSlaReminders extends Command
{
    protected $signature = 'approvals:sla-reminders';
    protected $description = 'Send reminders for approvals approaching SLA deadline';

    public function handle(): int
    {
        $threshold = now()->addHours(4);

        $approaching = ApprovalInstance::pending()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', $threshold)
            ->where('sla_due_at', '>', now())
            ->get();

        foreach ($approaching as $instance) {
            $step = $instance->currentStep();
            // Send reminder to approvers
        }

        return Command::SUCCESS;
    }
}
```

## SLA data points available

| Where | Column | What it represents |
|---|---|---|
| `ApprovalInstance` | `sla_due_at` | Total deadline for the entire workflow |
| `ApprovalStep` | `sla_hours` | SLA budget for this individual step (in hours) |
| `ApprovalStep` | `sla_due_at` | Individual deadline for this step |
| `ApprovalStep` | `started_at` | When this step was activated |

## Marking workflows as expired

If you have a scheduled task that marks workflows as expired past their SLA:

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;

public function markExpired(): void
{
    $overdue = ApprovalInstance::overdue()->get();

    foreach ($overdue as $instance) {
        // Cancel with a system actor, or use a dedicated expire method
        Approval::cancel($instance, $systemUser, remarks: 'Expired due to SLA breach.');
    }
}
```

> **Note:** The `Expired` status exists in the `ApprovalStatus` enum but is not automatically applied. Your application decides when and how to move workflows into the expired state.

## Configuration reference

```php
'sla' => [
    'default_hours' => 48,   // global default per step
    'timezone'      => 'UTC', // timezone for deadline computations
],
```

You can omit `sla_hours` entirely from both config and the builder. Steps with a null `sla_hours` will have a null `sla_due_at`, and the `isOverdue()` / `overdue()` helpers will not flag them.