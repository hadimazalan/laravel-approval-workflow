# Custom Approver Resolvers

The `ApproverResolver` contract determines who can act on each workflow step. The package ships with a built-in resolver that handles the common cases, but you can plug in your own for custom logic.

## The contract

```php
namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;

interface ApproverResolver
{
    /** @return array<int, Model|object> */
    public function resolve(ApprovalStep $step): array;
}
```

The resolver receives the step (with `$step->instance` accessible via the relationship) and must return an array of approver objects. Each returned object is used for:

- **Authorization** — only users in this list (or their delegates) can approve/reject the step.
- **Notifications** — each user is notified via the configured channels.
- **Audit** — the actor's class and primary key are recorded.

## Built-in resolver: `ConfiguredApproverResolver`

The default resolver (`Hadimazalan\ApprovalWorkflow\Resolvers\ConfiguredApproverResolver`) dispatches based on how you configured each level in the fluent builder.

### Explicit user IDs

```php
Approval::for($claim)
    ->level('Head of Department')
    ->approvers([1, 2, 3])        // stored in the step definition
    ->start();
```

The resolver reads the step's `approvers` column (a JSON array of primary keys) and queries the configured `approver_model`.

### Role

```php
Approval::for($claim)
    ->level('Finance')
    ->byRole('finance-manager')
    ->start();
```

Uses `$model::whereHas('roles', fn $q => $q->where('name', 'finance-manager'))`. Requires a `roles()` relationship on your approver model.

### Department

```php
Approval::for($claim)
    ->level('Head of Department')
    ->byDepartment('engineering')
    ->start();
```

Uses `$model::whereHas('departments', fn $q => $q->where('name', 'engineering'))`. Requires a `departments()` relationship.

### Role and department (intersection)

```php
Approval::for($claim)
    ->level('Regional Manager')
    ->byRoleAndDepartment('manager', 'asia-pacific')
    ->start();
```

Only users who have both the role and belong to the department are returned.

### Closure

```php
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

Approval::for($claim)
    ->level('Custom')
    ->resolveUsing(function (ApprovalStep $step) {
        return User::where('tenant_id', $step->instance->approvable->tenant_id)
            ->where('is_approver', true)
            ->get()
            ->all();
    })
    ->start();
```

Closures are serialized and stored in the step's metadata. They are invoked at step activation time with access to the `ApprovalStep`.

> **Note:** Closures are resolved at activation time (when the step becomes `Active`), not at workflow creation. If the list of eligible approvers changes between creation and activation, the closure picks up the current state.

### Fallback: stored approvers

If none of the resolver modes match, the resolver falls back to reading the step's `approvers` column directly (the array of explicit IDs set via `->approvers([...])`).

## Writing a custom resolver

Implement the `ApproverResolver` interface and register it in the config.

### Example: tenant-scoped resolver

```php
namespace App\Workflow\Resolvers;

use Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use App\Models\User;

class TenantApproverResolver implements ApproverResolver
{
    public function resolve(ApprovalStep $step): array
    {
        $role = $step->metadata['resolver']['role'] ?? null;

        if (! $role) {
            return [];
        }

        $tenantId = $step->instance->approvable->tenant_id;

        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', $role))
            ->get()
            ->all();
    }
}
```

### Example: API-backed resolver

```php
namespace App\Workflow\Resolvers;

use Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Support\Facades\Http;

class ExternalApproverResolver implements ApproverResolver
{
    public function resolve(ApprovalStep $step): array
    {
        $response = Http::get(config('services.approval.api_url'), [
            'workflow' => $step->instance->name,
            'level'    => $step->level,
        ]);

        // The external service returns user IDs. Use the configured approver_model.
        $model = config('approval-workflow.approver_model');

        return $model::whereIn('id', $response->json('approvers'))->get()->all();
    }
}
```

## Registering a custom resolver

Update `config/approval-workflow.php`:

```php
'resolver' => App\Workflow\Resolvers\TenantApproverResolver::class,
```

Now every workflow uses your resolver. You can still use different resolution strategies per level — the resolver receives the full `ApprovalStep` with its metadata, so it can branch on `$step->metadata['resolver']` however it likes.

## Testing resolvers

The `ConfiguredApproverResolver` receives the step after it is persisted but before activation. To test your own resolver:

```php
public function test_custom_resolver_returns_expected_approvers(): void
{
    $resolver = new TenantApproverResolver();
    $step = ApprovalStep::find(1); // persisted step

    $approvers = $resolver->resolve($step);

    $this->assertCount(2, $approvers);
    $this->assertInstanceOf(User::class, $approvers[0]);
}
```