<?php

namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

/**
 * Issues and verifies OTP challenges for high-value approval steps.
 *
 * The default implementation is a no-op (see NullOtpChallengeProvider). Bind
 * your own implementation to enforce OTP for one or more workflow levels.
 */
interface OtpChallengeProvider
{
    /**
     * Issue a challenge for the given approver on the given step.
     * Implementations should dispatch a delivery message (SMS, email, etc.)
     * and return the challenge identifier (or a token) so the caller can
     * later verify it.
     */
    public function issue(object $approver, ApprovalStep $step): string;

    /**
     * Verify a code against the active challenge. Returns true on success.
     */
    public function verify(ApprovalStep $step, object $approver, string $code): bool;

    /**
     * Whether OTP is enforced at all. Implementations may toggle this at
     * runtime based on environment, step configuration, or workflow type.
     */
    public function enabled(ApprovalStep $step): bool;
}
