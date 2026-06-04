<?php

namespace Hadimazalan\ApprovalWorkflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata'   => 'array',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approval-workflow.tables.delegations', 'approval_delegations');
    }

    public function getConnectionName(): ?string
    {
        return config('approval-workflow.connection');
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ApprovalStep::class, 'step_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
