<?php

namespace Hadimazalan\ApprovalWorkflow\Models;

use Hadimazalan\ApprovalWorkflow\Enums\ApprovalActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    protected $guarded = ['id'];

    public $timestamps = true;

    protected $casts = [
        'type'    => ApprovalActionType::class,
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('approval-workflow.tables.actions', 'approval_actions');
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
}
