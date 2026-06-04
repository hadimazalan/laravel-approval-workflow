<?php

namespace Hadimazalan\ApprovalWorkflow\Facades;

use Hadimazalan\ApprovalWorkflow\ApprovalBuilder;
use Hadimazalan\ApprovalWorkflow\ApprovalManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Hadimazalan\ApprovalWorkflow\ApprovalBuilder for(\Illuminate\Database\Eloquent\Model $model)
 * @method static \Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance approve(\Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance $instance, mixed $approver, ?string $remarks = null, ?string $otp = null)
 * @method static \Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance reject(\Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance $instance, mixed $approver, ?string $remarks = null)
 * @method static \Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance delegate(\Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance $instance, mixed $fromUser, mixed $toUser, ?string $reason = null, ?\DateTimeInterface $expiresAt = null)
 *
 * @see \Hadimazalan\ApprovalWorkflow\ApprovalManager
 */
class Approval extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'approval';
    }
}
