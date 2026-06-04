<?php

namespace Hadimazalan\ApprovalWorkflow;

use Hadimazalan\ApprovalWorkflow\Contracts\AuditLogger;
use Hadimazalan\ApprovalWorkflow\Contracts\NotificationChannel;
use Hadimazalan\ApprovalWorkflow\Contracts\OtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStatus;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalDelegation;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Hadimazalan\ApprovalWorkflow\Notifications\ApprovalRequestedNotification;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use RuntimeException;

class ApprovalManager
{
    /**
     * @param  array<string, NotificationChannel>  $channels
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected \Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver $resolver,
        protected OtpChallengeProvider $otp,
        protected AuditLogger $audit,
        protected array $channels,
        protected array $config = [],
    ) {
    }

    public function for(Model $approvable): ApprovalBuilder
    {
        return new ApprovalBuilder($this, $approvable);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    public function start(Model $approvable, array $definition): ApprovalInstance
    {
        $levels = $definition['levels'] ?? [];
        $defaultSla = $this->config['sla']['default_hours'] ?? null;

        $instance = new ApprovalInstance;
        $instance->approvable_type = $approvable->getMorphClass();
        $instance->approvable_id = $approvable->getKey();
        $instance->name = $definition['name'] ?? null;
        $instance->status = ApprovalStatus::Pending;
        $instance->current_level = 0;
        $instance->total_levels = count($levels);
        $instance->metadata = $definition['metadata'] ?? [];
        $instance->sla_due_at = $this->computeSla($levels, $defaultSla);
        $instance->started_at = Carbon::now();
        $instance->save();

        $levelIndex = 0;
        foreach ($levels as $level) {
            /** @var ApprovalStep $step */
            $step = $instance->steps()->create([
                'level'       => ++$levelIndex,
                'name'        => $level['name'] ?? "Level {$levelIndex}",
                'status'      => ApprovalStepStatus::Pending,
                'sla_hours'   => $level['sla_hours'] ?? $defaultSla,
                'approvers'   => $level['approvers'] ?? null,
                'metadata'    => $level['metadata'] ?? [],
                'otp_required' => (bool) ($level['otp'] ?? false),
            ]);
        }

        $this->audit->record($instance, null, $approvable, ApprovalActionType::Created, 'Workflow started.', [
            'levels' => $levels,
        ]);

        // Activate the first step and notify its approvers.
        $this->activateStep($instance, $instance->steps()->first());

        return $instance->fresh('steps');
    }

    public function approve(ApprovalInstance $instance, object $approver, ?string $remarks = null, ?string $otp = null): ApprovalInstance
    {
        $step = $this->currentStepOrFail($instance);

        $this->assertCanAct($instance, $step, $approver);
        $this->assertOtp($step, $approver, $otp);

        $step->status = ApprovalStepStatus::Approved;
        $step->decided_at = Carbon::now();
        $step->save();

        $this->audit->record($instance, $step, $approver, ApprovalActionType::Approved, $remarks);

        $next = $instance->steps()->where('level', $step->level + 1)->first();
        if ($next) {
            $this->activateStep($instance, $next);
        } else {
            $this->complete($instance, ApprovalStatus::Approved, $approver, $remarks);
        }

        return $instance->fresh('steps');
    }

    public function reject(ApprovalInstance $instance, object $approver, ?string $remarks = null): ApprovalInstance
    {
        $step = $this->currentStepOrFail($instance);

        $this->assertCanAct($instance, $step, $approver);
        $this->assertOtp($step, $approver, null);

        $step->status = ApprovalStepStatus::Rejected;
        $step->decided_at = Carbon::now();
        $step->save();

        $this->audit->record($instance, $step, $approver, ApprovalActionType::Rejected, $remarks);

        $this->terminate($instance, ApprovalStatus::Rejected, $approver, $remarks);

        return $instance->fresh('steps');
    }

    /**
     * Mark a workflow as completed and skip any remaining steps.
     */
    protected function terminate(ApprovalInstance $instance, ApprovalStatus $status, object $actor, ?string $remarks): void
    {
        $instance->status = $status;
        $instance->completed_at = Carbon::now();
        $instance->save();

        $instance->steps()
            ->whereIn('status', [ApprovalStepStatus::Pending, ApprovalStepStatus::Active])
            ->update(['status' => ApprovalStepStatus::Skipped]);
    }

    public function delegate(
        ApprovalInstance $instance,
        object $fromUser,
        object $toUser,
        ?string $reason = null,
        ?DateTimeInterface $expiresAt = null,
    ): ApprovalInstance {
        $step = $this->currentStepOrFail($instance);

        $this->assertCanAct($instance, $step, $fromUser);

        $delegation = new ApprovalDelegation;
        $delegation->instance_id = $instance->getKey();
        $delegation->step_id = $step->getKey();
        $delegation->from_user_type = $fromUser::class;
        $delegation->from_user_id = $fromUser->getKey();
        $delegation->to_user_type = $toUser::class;
        $delegation->to_user_id = $toUser->getKey();
        $delegation->reason = $reason;
        $delegation->expires_at = $expiresAt;
        $delegation->save();

        $this->audit->record($instance, $step, $fromUser, ApprovalActionType::Delegated, $reason, [
            'to_user_type' => $toUser::class,
            'to_user_id'   => $toUser->getKey(),
        ]);

        // Re-resolve approvers so the delegated user gets notified.
        $approvers = $this->resolver->resolve($step->fresh());

        if (! empty($approvers)) {
            $this->notifyApprovers($instance, $step->fresh(), $approvers);
        }

        return $instance->fresh('steps');
    }

    public function cancel(ApprovalInstance $instance, object $actor, ?string $remarks = null): ApprovalInstance
    {
        if ($instance->isComplete()) {
            return $instance;
        }

        $this->terminate($instance, ApprovalStatus::Cancelled, $actor, $remarks);

        $this->audit->record($instance, $instance->currentStep(), $actor, ApprovalActionType::Cancelled, $remarks);

        return $instance->fresh('steps');
    }

    /**
     * Activate a step: mark it Active, persist resolved approvers, and
     * dispatch notifications.
     */
    public function activateStep(ApprovalInstance $instance, ApprovalStep $step): void
    {
        $step->status = ApprovalStepStatus::Active;
        $step->started_at = Carbon::now();

        if ($step->sla_hours !== null) {
            $step->sla_due_at = Carbon::now()->addHours((int) $step->sla_hours);
        }

        $approvers = $this->resolver->resolve($step);
        $step->approvers = $this->idsOf($approvers);
        $step->save();

        $instance->current_level = $step->level;
        $instance->save();

        if (! empty($approvers)) {
            $this->notifyApprovers($instance, $step, $approvers);
        }
    }

    /**
     * @param  array<int, object>  $approvers
     */
    public function notifyApprovers(ApprovalInstance $instance, ApprovalStep $step, array $approvers): void
    {
        $notification = new ApprovalRequestedNotification($instance, $step);
        $channels = $this->resolveChannels($step);

        if (empty($channels)) {
            return;
        }

        foreach ($approvers as $approver) {
            foreach ($channels as $channel) {
                try {
                    $channel->send($approver, $notification, $instance, $step);
                } catch (\Throwable $e) {
                    // Never let a notification failure kill the workflow.
                    $this->audit->record($instance, $step, $approver, ApprovalActionType::Notified, null, [
                        'channel' => $channel->name(),
                        'error'   => $e->getMessage(),
                    ]);
                    continue;
                }

                $this->audit->record($instance, $step, $approver, ApprovalActionType::Notified, null, [
                    'channel' => $channel->name(),
                ]);
            }
        }
    }

    /**
     * @return array<int, NotificationChannel>
     */
    protected function resolveChannels(ApprovalStep $step): array
    {
        $aliases = $this->config['channel_aliases'] ?? [];

        $requested = $step->metadata['channels'] ?? null;
        if (! is_array($requested) || empty($requested)) {
            return [];
        }

        $resolved = [];
        foreach ($requested as $name) {
            $name = $aliases[$name] ?? $name;
            if (isset($this->channels[$name])) {
                $resolved[] = $this->channels[$name];
            }
        }

        return $resolved;
    }

    protected function complete(ApprovalInstance $instance, ApprovalStatus $status, object $actor, ?string $remarks): void
    {
        $instance->status = $status;
        $instance->completed_at = Carbon::now();
        $instance->save();
    }

    protected function currentStepOrFail(ApprovalInstance $instance): ApprovalStep
    {
        $step = $instance->currentStep();

        if (! $step) {
            throw new RuntimeException('No active step for this approval instance.');
        }

        return $step;
    }

    protected function assertCanAct(ApprovalInstance $instance, ApprovalStep $step, object $approver): void
    {
        if (! $instance->status instanceof ApprovalStatus || $instance->status->isTerminal()) {
            throw new RuntimeException('This approval workflow is no longer pending.');
        }

        if (! $step->isActive()) {
            throw new RuntimeException("Step {$step->level} is not active.");
        }

        $approverId = $this->idOf($approver);
        $allowed = $step->approvers ?? [];

        if (! in_array($approverId, array_map(fn ($v) => is_object($v) ? $v : (int) $v, $allowed), true)) {
            // Delegation: if the approver is the target of an active delegation on this step, allow it.
            $delegated = ApprovalDelegation::query()
                ->where('step_id', $step->getKey())
                ->where('to_user_type', $approver::class)
                ->where('to_user_id', $approverId)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
                })
                ->exists();

            if (! $delegated) {
                throw new RuntimeException('This approver is not allowed to act on this step.');
            }
        }
    }

    protected function assertOtp(ApprovalStep $step, object $approver, ?string $code): void
    {
        if (! $step->otp_required || ! $this->otp->enabled($step)) {
            return;
        }

        if ($code === null || $code === '') {
            throw new RuntimeException('An OTP code is required to perform this action.');
        }

        if (! $this->otp->verify($step, $approver, $code)) {
            $this->audit->record($step->instance, $step, $approver, ApprovalActionType::OtpFailed);

            throw new RuntimeException('Invalid OTP code.');
        }
    }

    /**
     * @param  array<int, object>  $approvers
     * @return array<int, mixed>
     */
    protected function idsOf(array $approvers): array
    {
        return array_values(array_map(fn ($a) => $this->idOf($a), $approvers));
    }

    protected function idOf(object $model): mixed
    {
        if (method_exists($model, 'getKey')) {
            return $model->getKey();
        }

        return null;
    }

    protected function computeSla(array $levels, ?int $defaultHours): ?CarbonInterface
    {
        $hours = 0;
        foreach ($levels as $level) {
            $hours += $level['sla_hours'] ?? $defaultHours ?? 0;
        }

        return $hours > 0 ? Carbon::now()->addHours($hours) : null;
    }
}
