<?php

namespace Hadimazalan\ApprovalWorkflow\Enums;

enum ApprovalStepStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Skipped   = 'skipped';
    case Delegated = 'delegated';
    case Expired   = 'expired';
}
