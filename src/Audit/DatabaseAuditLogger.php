<?php

namespace Hadimazalan\ApprovalWorkflow\Audit;

use Hadimazalan\ApprovalWorkflow\Contracts\AuditLogger;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalAction;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Support\Carbon;

class DatabaseAuditLogger implements AuditLogger
{
    public function record(
        ApprovalInstance $instance,
        ?ApprovalStep $step,
        ?object $actor,
        ApprovalActionType $type,
        ?string $remarks = null,
        array $context = [],
    ): void {
        $action = new ApprovalAction;
        $action->instance_id = $instance->getKey();
        $action->step_id = $step?->getKey();
        $action->type = $type;
        $action->remarks = $remarks;
        $action->context = $context;
        $action->occurred_at = Carbon::now();

        if ($actor !== null) {
            $action->actor_type = $actor::class;
            $action->actor_id = $actor->getKey();
        }

        $action->save();
    }
}
