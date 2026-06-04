<?php

namespace Hadimazalan\ApprovalWorkflow\Enums;

enum ApprovalStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Cancelled = 'cancelled';
    case Expired   = 'expired';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled, self::Expired => true,
            default => false,
        };
    }
}
