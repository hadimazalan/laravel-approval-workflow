# Models & Relationships

The package ships with four Eloquent models. All table names and the database connection are configurable via `config('approval-workflow.tables.*')` and `config('approval-workflow.connection')`.

## ApprovalInstance

The root model representing one workflow execution. Belongs polymorphically to your application model.

**Table:** `approval_instances` (configurable)

### Columns

| Column | Type | Description |
|---|---|---|
| `id` | bigint, PK | Auto-increment |
| `approvable_type` | string | Morphs — the class of the related model |
| `approvable_id` | bigint | Morphs — the ID of the related model |
| `name` | string, nullable | Human-readable workflow name |
| `status` | string, default `'pending'` | Indexed. See [ApprovalStatus enum](#approvalstatus-enum) |
| `current_level` | unsigned int, default `0` | The currently active step number |
| `total_levels` | unsigned int, default `0` | Total number of steps in the workflow |
| `metadata` | json, nullable | Free-form data set via `->withMetadata()` |
| `started_at` | timestamp, nullable | When the workflow was started |
| `completed_at` | timestamp, nullable | When the workflow reached a terminal state |
| `sla_due_at` | timestamp, nullable | Indexed. Combined SLA deadline across all levels |
| `created_at` | timestamp | Laravel timestamp |
| `updated_at` | timestamp | Laravel timestamp |

### Relationships

| Relation | Type | Description |
|---|---|---|
| `approvable()` | `MorphTo` | The application model this workflow governs (Claim, Invoice, etc.) |
| `steps()` | `HasMany` | All steps, ordered by `level` |
| `actions()` | `HasMany` | All audit actions for this workflow |
| `delegations()` | `HasMany` | All delegations on this workflow |

### Methods

```php
$instance->currentStep(): ?ApprovalStep
```
Returns the step with status `Active`, or `null` if no step is active.

```php
$instance->isComplete(): bool
```
Returns `true` if the status is a terminal state (approved, rejected, cancelled, expired).

```php
$instance->isOverdue(): bool
```
Returns `true` if the SLA deadline has passed and the workflow is not yet complete.

### Scopes

```php
ApprovalInstance::pending()->get();
```
Filters to instances with status `'pending'`.

```php
ApprovalInstance::overdue()->get();
```
Filters to pending instances whose `sla_due_at` is in the past.

### Adding the relationship to your model

Add a `morphOne` relationship on your application model to easily navigate from your model to its workflow:

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

Usage:

```php
$claim->approvalInstance;           // the ApprovalInstance
$claim->approvalInstance->steps;    // all steps
$claim->approvalInstance->status;   // 'pending', 'approved', etc.
```

## ApprovalStep

A single level in a workflow. Each `ApprovalInstance` has one step per level.

**Table:** `approval_steps` (configurable)

### Columns

| Column | Type | Description |
|---|---|---|
| `id` | bigint, PK | Auto-increment |
| `instance_id` | bigint, FK | Foreign key to `approval_instances`, cascade on delete |
| `level` | unsigned int | Step number (1-based). Unique with `instance_id` |
| `name` | string, nullable | Human-readable label ("Head of Department", "Finance", etc.) |
| `status` | string, default `'pending'` | Indexed. See [ApprovalStepStatus enum](#approvalstepstatus-enum) |
| `sla_hours` | unsigned int, nullable | SLA window for this step in hours |
| `approvers` | json, nullable | Resolved approver primary keys (populated on activation) |
| `metadata` | json, nullable | Per-level resolver configuration |
| `otp_required` | boolean, default `false` | Whether OTP is enforced on this step |
| `started_at` | timestamp, nullable | When the step was activated |
| `decided_at` | timestamp, nullable | When the step was approved/rejected |
| `sla_due_at` | timestamp, nullable | Indexed. Per-step SLA deadline |
| `created_at` | timestamp | Laravel timestamp |
| `updated_at` | timestamp | Laravel timestamp |

### Relationships

| Relation | Type | Description |
|---|---|---|
| `instance()` | `BelongsTo` | The parent `ApprovalInstance` |
| `actions()` | `HasMany` | Audit actions belonging to this step |

### Methods

```php
$step->isActive(): bool
```
Returns `true` if the step status is `Active`.

```php
$step->isOverdue(): bool
```
Returns `true` if the step's SLA deadline has passed and the step is currently active.

## ApprovalAction

An immutable audit record. One row is created for each workflow event.

**Table:** `approval_actions` (configurable)

### Columns

| Column | Type | Description |
|---|---|---|
| `id` | bigint, PK | Auto-increment |
| `instance_id` | bigint, FK | Foreign key to `approval_instances`, cascade on delete |
| `step_id` | bigint, FK, nullable | Foreign key to `approval_steps`, null on delete |
| `actor_type` | string, nullable | Morphs — the class of the actor |
| `actor_id` | bigint, nullable | Morphs — the ID of the actor |
| `type` | string | Indexed. See [ApprovalActionType enum](#approvalactiontype-enum) |
| `remarks` | string, nullable | Free-text comment |
| `context` | json, nullable | Additional data (channel name, error message, etc.) |
| `occurred_at` | timestamp, nullable | When the action occurred |
| `created_at` / `updated_at` | timestamp | Laravel timestamps |

### Relationships

| Relation | Type | Description |
|---|---|---|
| `instance()` | `BelongsTo` | The parent `ApprovalInstance` |
| `step()` | `BelongsTo` | The step this action relates to (nullable) |

The `actor` is polymorphic (nullable). It can be any model — a `User`, an admin, or even the system itself.

## ApprovalDelegation

Records that one user delegated their approval authority to another user.

**Table:** `approval_delegations` (configurable)

### Columns

| Column | Type | Description |
|---|---|---|
| `id` | bigint, PK | Auto-increment |
| `instance_id` | bigint, FK | Foreign key to `approval_instances`, cascade on delete |
| `step_id` | bigint, FK, nullable | Foreign key to `approval_steps`, null on delete |
| `from_user_type` | string, nullable | Morphs — the delegator's model class |
| `from_user_id` | bigint, nullable | Morphs — the delegator's ID |
| `to_user_type` | string, nullable | Morphs — the delegate's model class |
| `to_user_id` | bigint, nullable | Morphs — the delegate's ID |
| `reason` | string, nullable | Why the delegation was made |
| `metadata` | json, nullable | Free-form data |
| `expires_at` | timestamp, nullable | When the delegation expires |
| `revoked_at` | timestamp, nullable | When the delegation was revoked (if any) |
| `created_at` / `updated_at` | timestamp | Laravel timestamps |

### Relationships

| Relation | Type | Description |
|---|---|---|
| `instance()` | `BelongsTo` | The parent `ApprovalInstance` |
| `step()` | `BelongsTo` | The step this delegation applies to |

### Methods

```php
$delegation->isActive(): bool
```
Returns `true` if the delegation is not revoked and (if set) has not expired.

## Enums

### ApprovalStatus

String-backed enum used on `ApprovalInstance::status`.

| Case | Value | Terminal? |
|---|---|---|
| `Pending` | `'pending'` | No |
| `Approved` | `'approved'` | Yes |
| `Rejected` | `'rejected'` | Yes |
| `Cancelled` | `'cancelled'` | Yes |
| `Expired` | `'expired'` | Yes |

```php
$instance->status->isTerminal(); // bool
```

### ApprovalStepStatus

String-backed enum used on `ApprovalStep::status`.

| Case | Value |
|---|---|
| `Pending` | `'pending'` |
| `Active` | `'active'` |
| `Approved` | `'approved'` |
| `Rejected` | `'rejected'` |
| `Skipped` | `'skipped'` |
| `Delegated` | `'delegated'` |
| `Expired` | `'expired'` |

### ApprovalActionType

String-backed enum used on `ApprovalAction::type`.

| Case | Value |
|---|---|
| `Created` | `'created'` |
| `Approved` | `'approved'` |
| `Rejected` | `'rejected'` |
| `Delegated` | `'delegated'` |
| `Cancelled` | `'cancelled'` |
| `Expired` | `'expired'` |
| `OtpSent` | `'otp_sent'` |
| `OtpFailed` | `'otp_failed'` |
| `Notified` | `'notified'` |

## Database diagram

```
approval_instances
├── approvable (polymorphic)  →  your model (Claim, Invoice, etc.)
├── steps (hasMany)           →  approval_steps
│   └── actions (hasMany)     →  approval_actions
└── delegations (hasMany)     →  approval_delegations
```