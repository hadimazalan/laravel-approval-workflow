<?php

namespace Hadimazalan\ApprovalWorkflow\Tests\Feature;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStatus;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Hadimazalan\ApprovalWorkflow\Facades\Approval;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Tests\ClaimStub;
use Hadimazalan\ApprovalWorkflow\Tests\TestCase;
use Hadimazalan\ApprovalWorkflow\Tests\UserStub;

class StartApprovalWorkflowTest extends TestCase
{
    public function test_can_start_a_multi_level_workflow(): void
    {
        $claim = ClaimStub::create(['title' => 'Travel claim']);

        $instance = Approval::for($claim)
            ->name('Travel claim approval')
            ->level('Head of Department')
            ->level('Finance')
            ->level('CEO')
            ->start();

        $this->assertInstanceOf(ApprovalInstance::class, $instance);
        $this->assertSame(ApprovalStatus::Pending, $instance->status);
        $this->assertSame(3, $instance->total_levels);
        $this->assertSame(1, $instance->current_level);
        $this->assertCount(3, $instance->steps);

        $first = $instance->steps->first();
        $this->assertSame(ApprovalStepStatus::Active, $first->status);
        $this->assertSame(1, $first->level);
    }

    public function test_first_step_uses_resolver_configured_approvers(): void
    {
        $hod = UserStub::create(['name' => 'Alice', 'email' => 'alice@example.com']);
        UserStub::create(['name' => 'Bob', 'email' => 'bob@example.com']);

        $claim = ClaimStub::create(['title' => 'Expense']);

        $instance = Approval::for($claim)
            ->level('Head of Department')
            ->approvers([$hod->id])
            ->start();

        $step = $instance->steps->first();
        $this->assertSame([$hod->id], $step->approvers);
    }

    public function test_sla_due_at_is_computed_from_levels(): void
    {
        $claim = ClaimStub::create(['title' => 'Purchase order']);

        $instance = Approval::for($claim)
            ->level('Finance')->slaHours(24)
            ->level('CEO')->slaHours(48)
            ->start();

        $this->assertNotNull($instance->sla_due_at);
    }

    public function test_starting_a_workflow_records_an_audit_action(): void
    {
        $claim = ClaimStub::create(['title' => 'Audit me']);
        Approval::for($claim)->level('Finance')->start();

        $this->assertDatabaseHas('approval_actions', [
            'instance_id' => $claim->approvalInstance->id,
            'type'        => 'created',
        ]);
    }
}
