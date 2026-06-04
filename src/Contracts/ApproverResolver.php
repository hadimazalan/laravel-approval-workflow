<?php

namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the approvers for a given workflow step.
 *
 * Implementations are responsible for looking up users by role, department,
 * explicit user IDs, closures, or any other application-specific mechanism.
 * They MUST return an array of approver models (or any objects that the rest
 * of the pipeline can treat as approvers — for example, a custom user model).
 */
interface ApproverResolver
{
    /**
     * Resolve the approvers for the given step.
     *
     * @return array<int, Model|object>  List of approvers.
     */
    public function resolve(ApprovalStep $step): array;
}
