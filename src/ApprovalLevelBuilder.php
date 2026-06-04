<?php

namespace Hadimazalan\ApprovalWorkflow;

/**
 * Per-level configuration helper returned by ApprovalBuilder::level().
 *
 * The level definition array is mutated in place by these methods and is
 * later read by ApprovalManager::start(). For convenience, methods that
 * belong to the parent ApprovalBuilder (level, notifyBy, slaHours, name,
 * start) are proxied so that the user can write:
 *
 *   Approval::for($model)
 *       ->level('A')->byRole('x')
 *       ->level('B')->byDepartment('y')
 *       ->notifyBy(['email'])
 *       ->start();
 *
 * without needing an explicit ->done() call between levels.
 */
class ApprovalLevelBuilder
{
    /**
     * @param  array<string, mixed>  $level
     */
    public function __construct(
        protected array &$level,
        protected ApprovalBuilder $parent,
    ) {
    }

    /**
     * Explicit list of approver user IDs.
     *
     * @param  array<int, mixed>  $ids
     */
    public function approvers(array $ids): self
    {
        $this->level['approvers'] = $ids;

        return $this;
    }

    public function byRole(string $role): self
    {
        $this->level['metadata']['resolver'] = [
            'mode' => 'role',
            'role' => $role,
        ];

        return $this;
    }

    public function byDepartment(string $department): self
    {
        $this->level['metadata']['resolver'] = [
            'mode'       => 'department',
            'department' => $department,
        ];

        return $this;
    }

    public function byRoleAndDepartment(string $role, string $department): self
    {
        $this->level['metadata']['resolver'] = [
            'mode'       => 'role_and_department',
            'role'       => $role,
            'department' => $department,
        ];

        return $this;
    }

    /**
     * Use a fully custom closure to resolve approvers.
     *
     * @param  callable(\Hadimazalan\ApprovalWorkflow\Models\ApprovalStep): array  $callback
     */
    public function resolveUsing(callable $callback): self
    {
        $this->level['metadata']['resolver'] = [
            'mode'    => 'closure',
            'closure' => $callback,
        ];

        return $this;
    }

    public function slaHours(int $hours): self
    {
        $this->level['sla_hours'] = $hours;

        return $this;
    }

    public function requireOtp(bool $on = true): self
    {
        $this->level['otp'] = $on;

        return $this;
    }

    /**
     * Override the global notifyBy channels for this level only.
     *
     * @param  array<int, string>  $channels
     */
    public function channels(array $channels): self
    {
        $this->level['channels'] = $channels;

        return $this;
    }

    /**
     * Alias for ::channels() to mirror ApprovalBuilder::notifyBy().
     *
     * @param  array<int, string>  $channels
     */
    public function notifyBy(array $channels): self
    {
        return $this->channels($channels);
    }

    /**
     * Return to the parent builder. Equivalent to chaining the next method
     * directly on the level builder, since ->level(), ->notifyBy(), etc.
     * are proxied.
     */
    public function done(): ApprovalBuilder
    {
        return $this->parent;
    }

    /**
     * Proxy: add another approval level.
     */
    public function level(string $name): ApprovalLevelBuilder
    {
        return $this->parent->level($name);
    }

    /**
     * Proxy: set the default channels used for every level that doesn't
     * override them.
     *
     * @param  array<int, string>  $channels
     */
    public function notifyByAll(array $channels): ApprovalBuilder
    {
        return $this->parent->notifyBy($channels);
    }

    /**
     * Proxy: set the workflow name.
     */
    public function name(string $name): ApprovalBuilder
    {
        return $this->parent->name($name);
    }

    /**
     * Proxy: start the workflow.
     */
    public function start(): \Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance
    {
        return $this->parent->start();
    }
}
