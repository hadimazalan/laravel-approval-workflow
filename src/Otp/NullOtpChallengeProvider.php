<?php

namespace Hadimazalan\ApprovalWorkflow\Otp;

use Hadimazalan\ApprovalWorkflow\Contracts\OtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

/**
 * No-op OTP provider. Used when OTP enforcement is disabled. Apps that want
 * OTP should bind their own OtpChallengeProvider implementation.
 */
class NullOtpChallengeProvider implements OtpChallengeProvider
{
    public function issue(object $approver, ApprovalStep $step): string
    {
        return '';
    }

    public function verify(ApprovalStep $step, object $approver, string $code): bool
    {
        return true;
    }

    public function enabled(ApprovalStep $step): bool
    {
        return false;
    }
}
