<?php

namespace Hadimazalan\ApprovalWorkflow\Resolvers;

use Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Resolves approvers from per-level configuration:
 *
 *  - Explicit user IDs: ->level()->approvers([1, 2, 3])
 *  - Role names:        ->level('Finance')->byRole('finance-manager')
 *  - Department names:  ->level('Head of Department')->byDepartment('engineering')
 *  - Closures:          ->level()->resolveUsing(fn ($step) => [...])
 *  - Custom classes:    register your own ApproverResolver in config.
 *
 * The config file's `approver_model` controls the Eloquent model class used
 * to look up users. If your application has multiple user models, write a
 * custom resolver instead.
 */
class ConfiguredApproverResolver implements ApproverResolver
{
    public function resolve(ApprovalStep $step): array
    {
        $config = $step->metadata['resolver'] ?? [];
        $mode = $config['mode'] ?? null;

        // Closures take precedence — they're application-specific.
        if (isset($config['closure']) && is_callable($config['closure'])) {
            return array_values((array) $config['closure']($step));
        }

        $model = $this->userModel();

        return match ($mode) {
            'users'       => $this->resolveUsers($model, $config['ids'] ?? []),
            'role'        => $this->resolveRole($model, $config['role'] ?? null),
            'department'  => $this->resolveDepartment($model, $config['department'] ?? null),
            'role_and_department' => $this->resolveRoleAndDepartment(
                $model,
                $config['role'] ?? null,
                $config['department'] ?? null,
            ),
            default => $this->fallback($step, $model),
        };
    }

    protected function userModel(): string
    {
        $model = config('approval-workflow.approver_model');

        if (! $model) {
            throw new InvalidArgumentException(
                'No approver_model is configured. Set config("approval-workflow.approver_model") '
                . 'to your user model class, or register a custom ApproverResolver.'
            );
        }

        return $model;
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, Model>
     */
    protected function resolveUsers(string $model, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $model::query()->whereIn((new $model)->getKeyName(), $ids)->get()->all();
    }

    protected function resolveRole(string $model, ?string $role): array
    {
        if (! $role) {
            return [];
        }

        return $this->relationLookup($model, 'roles', $role);
    }

    protected function resolveDepartment(string $model, ?string $department): array
    {
        if (! $department) {
            return [];
        }

        return $this->relationLookup($model, 'departments', $department);
    }

    protected function resolveRoleAndDepartment(string $model, ?string $role, ?string $department): array
    {
        if (! $role || ! $department) {
            return [];
        }

        $query = $model::query();

        if (method_exists($model, 'roles')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if (method_exists($model, 'departments')) {
            $query->whereHas('departments', fn ($q) => $q->where('name', $department));
        }

        return $query->get()->all();
    }

    protected function relationLookup(string $model, string $relation, string $value): array
    {
        if (! method_exists($model, $relation)) {
            return [];
        }

        return $model::query()
            ->whereHas($relation, fn ($q) => $q->where('name', $value))
            ->get()
            ->all();
    }

    /**
     * @return array<int, Model>
     */
    protected function fallback(ApprovalStep $step, string $model): array
    {
        // If the step has explicit approver IDs already stored, use those.
        $ids = $step->approvers ?? [];

        if (empty($ids)) {
            return [];
        }

        return $this->resolveUsers($model, $ids);
    }
}
