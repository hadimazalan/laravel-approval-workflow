<?php

namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

/**
 * Records immutable approval events. The default implementation writes rows
 * to the `approval_actions` table; you may bind your own (Elasticsearch,
 * external audit service, etc.).
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        ApprovalInstance $instance,
        ?ApprovalStep $step,
        ?object $actor,
        ApprovalActionType $type,
        ?string $remarks = null,
        array $context = [],
    ): void;
}
