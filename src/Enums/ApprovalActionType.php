<?php

namespace Hadimazalan\ApprovalWorkflow\Enums;

enum ApprovalActionType: string
{
    case Created   = 'created';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Delegated = 'delegated';
    case Cancelled = 'cancelled';
    case Expired   = 'expired';
    case OtpSent   = 'otp_sent';
    case OtpFailed = 'otp_failed';
    case Notified  = 'notified';
}
