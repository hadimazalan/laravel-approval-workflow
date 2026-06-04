<?php

namespace Hadimazalan\ApprovalWorkflow\Models;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ApprovalStep extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status'      => ApprovalStepStatus::class,
        'level'       => 'integer',
        'sla_hours'   => 'integer',
        'approvers'   => 'array',
        'metadata'    => 'array',
        'started_at'  => 'datetime',
        'decided_at'  => 'datetime',
        'sla_due_at'  => 'datetime',
        'otp_required' => 'boolean',
    ];

    public function getTable(): string
    {
        return config('approval-workflow.tables.steps', 'approval_steps');
    }

    public function getConnectionName(): ?string
    {
        return config('approval-workflow.connection');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'step_id');
    }

    public function isActive(): bool
    {
        return $this->status === ApprovalStepStatus::Active;
    }

    public function isOverdue(): bool
    {
        return $this->sla_due_at instanceof Carbon
            && $this->sla_due_at->isPast()
            && $this->isActive();
    }
}
