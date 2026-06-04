<?php

namespace Hadimazalan\ApprovalWorkflow\Tests\Feature;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStatus;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Hadimazalan\ApprovalWorkflow\Facades\Approval;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalAction;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Tests\ClaimStub;
use Hadimazalan\ApprovalWorkflow\Tests\TestCase;
use Hadimazalan\ApprovalWorkflow\Tests\UserStub;
use RuntimeException;

class ApproveRejectWorkflowTest extends TestCase
{
    public function test_can_approve_through_all_levels(): void
    {
        $hod  = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);
        $fin  = UserStub::create(['name' => 'Fin', 'email' => 'fin@example.com']);
        $ceo  = UserStub::create(['name' => 'CEO', 'email' => 'ceo@example.com']);

        $claim = ClaimStub::create(['title' => 'Approve me']);

        $instance = Approval::for($claim)
            ->level('HOD')->approvers([$hod->id])
            ->level('Finance')->approvers([$fin->id])
            ->level('CEO')->approvers([$ceo->id])
            ->start();

        $instance = Approval::approve($instance, $hod, remarks: 'OK');
        $this->assertSame(ApprovalStepStatus::Approved, $instance->steps[0]->status);
        $this->assertSame(2, $instance->current_level);

        $instance = Approval::approve($instance, $fin, remarks: 'OK');
        $this->assertSame(3, $instance->current_level);

        $instance = Approval::approve($instance, $ceo, remarks: 'Approved');
        $this->assertSame(ApprovalStatus::Approved, $instance->status);
        $this->assertNotNull($instance->completed_at);

        $this->assertSame(3, ApprovalAction::where('instance_id', $instance->id)->where('type', 'approved')->count());
    }

    public function test_rejection_terminates_the_workflow(): void
    {
        $hod = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);
        $fin = UserStub::create(['name' => 'Fin', 'email' => 'fin@example.com']);

        $claim = ClaimStub::create(['title' => 'Reject me']);

        $instance = Approval::for($claim)
            ->level('HOD')->approvers([$hod->id])
            ->level('Finance')->approvers([$fin->id])
            ->start();

        $instance = Approval::reject($instance, $hod, remarks: 'No evidence');

        $this->assertSame(ApprovalStatus::Rejected, $instance->status);
        $this->assertSame(ApprovalStepStatus::Rejected, $instance->steps[0]->status);
        $this->assertSame(ApprovalStepStatus::Skipped, $instance->steps[1]->status);
    }

    public function test_unauthorized_approver_cannot_act(): void
    {
        $hod   = UserStub::create(['name' => 'HOD', 'email' => 'hod@example.com']);
        $other = UserStub::create(['name' => 'X', 'email' => 'x@example.com']);

        $claim = ClaimStub::create(['title' => 'Protected']);

        $instance = Approval::for($claim)
            ->level('HOD')->approvers([$hod->id])
            ->start();

        $this->expectException(RuntimeException::class);
        Approval::approve($instance, $other, remarks: 'I am not the HOD');
    }
}
