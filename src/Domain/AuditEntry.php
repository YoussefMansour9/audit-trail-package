<?php

declare(strict_types=1);

namespace AuditTrail\Domain;

use AuditTrail\Domain\Exception\InvalidActionException;

final class AuditEntry
{
    /** @var array<string, mixed>|null */
    private ?array $oldState;

    /** @var array<string, mixed>|null */
    private ?array $newState;

    /** @var array<string, mixed>|null */
    private ?array $metadata;

    private string $id;
    private string $aggregateType;
    private string $aggregateId;
    private Action $action;
    private string $performedBy;
    private \DateTimeImmutable $performedAt;

    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     * @param array<string, mixed>|null $metadata
     */
    private function __construct(
        string $id,
        string $aggregateType,
        string $aggregateId,
        Action $action,
        ?array $oldState,
        ?array $newState,
        string $performedBy,
        \DateTimeImmutable $performedAt,
        ?array $metadata,
    ) {
        $this->id = $id;
        $this->aggregateType = $aggregateType;
        $this->aggregateId = $aggregateId;
        $this->action = $action;
        $this->oldState = $oldState;
        $this->newState = $newState;
        $this->performedBy = $performedBy;
        $this->performedAt = $performedAt;
        $this->metadata = $metadata;
    }

    /**
     * Create a new audit entry (timestamp auto-set to now).
     *
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     * @param array<string, mixed>|null $metadata
     */
    public static function record(
        string $id,
        string $aggregateType,
        string $aggregateId,
        string $action,
        ?array $oldState,
        ?array $newState,
        string $performedBy,
        ?array $metadata = null,
    ): self {
        return new self(
            $id,
            $aggregateType,
            $aggregateId,
            self::resolveAction($action),
            $oldState,
            $newState,
            $performedBy,
            new \DateTimeImmutable(),
            $metadata,
        );
    }

    /**
     * Reconstruct an audit entry from stored data.
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException when required keys are missing
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'aggregate_type', 'aggregate_id', 'action', 'performed_by', 'performed_at'] as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in audit entry data', $key));
            }
        }

        return new self(
            $data['id'],
            $data['aggregate_type'],
            $data['aggregate_id'],
            self::resolveAction($data['action']),
            $data['old_state'] ?? null,
            $data['new_state'] ?? null,
            $data['performed_by'],
            new \DateTimeImmutable($data['performed_at']),
            $data['metadata'] ?? null,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function action(): Action
    {
        return $this->action;
    }

    /** @return array<string, mixed>|null */
    public function oldState(): ?array
    {
        return $this->oldState;
    }

    /** @return array<string, mixed>|null */
    public function newState(): ?array
    {
        return $this->newState;
    }

    public function performedBy(): string
    {
        return $this->performedBy;
    }

    public function performedAt(): \DateTimeImmutable
    {
        return $this->performedAt;
    }

    /** @return array<string, mixed>|null */
    public function metadata(): ?array
    {
        return $this->metadata;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'aggregate_type' => $this->aggregateType,
            'aggregate_id' => $this->aggregateId,
            'action' => $this->action->value,
            'old_state' => $this->oldState,
            'new_state' => $this->newState,
            'performed_by' => $this->performedBy,
            'performed_at' => $this->performedAt->format('Y-m-d\TH:i:s.uP'),
            'metadata' => $this->metadata,
        ];
    }

    private static function resolveAction(string $action): Action
    {
        $normalized = strtoupper(trim($action));

        return match ($normalized) {
            'CREATE' => Action::CREATE,
            'UPDATE' => Action::UPDATE,
            'DELETE' => Action::DELETE,
            default => throw new InvalidActionException($action),
        };
    }
}
