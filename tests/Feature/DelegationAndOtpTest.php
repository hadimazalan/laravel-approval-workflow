<?php

namespace Hadimazalan\ApprovalWorkflow\Tests\Feature;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Hadimazalan\ApprovalWorkflow\Facades\Approval;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalDelegation;
use Hadimazalan\ApprovalWorkflow\Tests\ClaimStub;
use Hadimazalan\ApprovalWorkflow\Tests\TestCase;
use Hadimazalan\ApprovalWorkflow\Tests\UserStub;
use RuntimeException;

class DelegationAndOtpTest extends TestCase
{
    public function test_delegation_records_a_row_and_allows_delegate_to_act(): void
    {
        $hod   = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);
        $proxy = UserStub::create(['name' => 'Proxy', 'email' => 'proxy@example.com']);

        $claim = ClaimStub::create(['title' => 'Delegate me']);

        $instance = Approval::for($claim)
            ->level('HOD')->approvers([$hod->id])
            ->start();

        $instance = Approval::delegate($instance, $hod, $proxy, reason: 'On leave');

        $this->assertSame(1, ApprovalDelegation::where('instance_id', $instance->id)->count());
        $this->assertTrue(
            ApprovalDelegation::where('instance_id', $instance->id)->first()->isActive()
        );

        // The proxy can now act on the HOD's behalf.
        $instance = Approval::approve($instance, $proxy, remarks: 'OK from proxy');
        $this->assertSame(ApprovalStepStatus::Approved, $instance->steps[0]->status);
    }

    public function test_otp_is_required_when_step_requires_it(): void
    {
        $hod = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);

        $claim = ClaimStub::create(['title' => 'Sensitive']);

        $instance = Approval::for($claim)
            ->level('HOD')
            ->approvers([$hod->id])
            ->requireOtp()
            ->start();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An OTP code is required');
        Approval::approve($instance, $hod, remarks: 'no otp');
    }

    public function test_otp_passes_when_correct_code_supplied(): void
    {
        $hod = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);

        $claim = ClaimStub::create(['title' => 'Sensitive 2']);

        $instance = Approval::for($claim)
            ->level('HOD')
            ->approvers([$hod->id])
            ->requireOtp()
            ->start();

        // The NullOtpStub.verify() always returns true, so any code works.
        $instance = Approval::approve($instance, $hod, remarks: 'with otp', otp: '000000');
        $this->assertSame(ApprovalStepStatus::Approved, $instance->steps[0]->status);
    }
}
