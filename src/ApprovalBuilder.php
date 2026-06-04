<?php

namespace Hadimazalan\ApprovalWorkflow;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Fluent builder used by the Approval facade:
 *
 *   Approval::for($claim)
 *       ->level('Head of Department')
 *       ->level('Finance')
 *       ->notifyBy(['email', 'whatsapp'])
 *       ->start();
 */
class ApprovalBuilder
{
    /** @var array<int, array<string, mixed>> */
    protected array $levels = [];

    /** @var array<int, string> */
    protected array $channels = [];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    protected ?int $slaHours = null;

    protected ?string $name = null;

    public function __construct(
        protected ApprovalManager $manager,
        protected Model $approvable,
    ) {
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Add an approval level. The first argument is a human-friendly label.
     * Subsequent calls chain configuration on the level.
     */
    public function level(string $name): ApprovalLevelBuilder
    {
        $level = [
            'name'      => $name,
            'metadata'  => [],
            'channels'  => null,
            'approvers' => null,
            'sla_hours' => null,
            'otp'       => false,
        ];

        $this->levels[] = &$level;

        return new ApprovalLevelBuilder($level, $this);
    }

    /**
     * Override the default SLA hours for all levels.
     */
    public function slaHours(int $hours): static
    {
        $this->slaHours = $hours;

        return $this;
    }

    /**
     * Channels to use for every level. Per-level ->channels(...) wins.
     *
     * @param  array<int, string>  $channels
     */
    public function notifyBy(array $channels): static
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * Free-form metadata stored on the instance.
     */
    public function withMetadata(array $metadata): static
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    public function start(): ApprovalInstance
    {
        if (empty($this->levels)) {
            throw new InvalidArgumentException('You must define at least one approval level.');
        }

        return $this->manager->start($this->approvable, $this->buildDefinition());
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDefinition(): array
    {
        $levels = array_map(function (array $level): array {
            $level['channels'] = $level['channels'] ?? $this->channels;
            $level['sla_hours'] = $level['sla_hours'] ?? $this->slaHours;

            return $level;
        }, $this->levels);

        return [
            'name'     => $this->name,
            'levels'   => $levels,
            'metadata' => $this->metadata,
        ];
    }
}
