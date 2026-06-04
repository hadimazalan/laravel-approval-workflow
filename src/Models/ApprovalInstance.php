<?php

namespace Hadimazalan\ApprovalWorkflow\Models;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStatus;
use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class ApprovalInstance extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status'             => ApprovalStatus::class,
        'current_level'      => 'integer',
        'total_levels'       => 'integer',
        'metadata'           => 'array',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'sla_due_at'         => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approval-workflow.tables.instances', 'approval_instances');
    }

    public function getConnectionName(): ?string
    {
        return config('approval-workflow.connection');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'instance_id')->orderBy('level');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'instance_id');
    }

    public function delegations(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'instance_id');
    }

    public function currentStep(): ?ApprovalStep
    {
        return $this->steps()->where('status', ApprovalStepStatus::Active)->first();
    }

    public function isComplete(): bool
    {
        return $this->status?->isTerminal() ?? false;
    }

    public function isOverdue(): bool
    {
        return $this->sla_due_at instanceof Carbon
            && $this->sla_due_at->isPast()
            && ! $this->isComplete();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }
}
