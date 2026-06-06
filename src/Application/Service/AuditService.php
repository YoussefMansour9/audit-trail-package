<?php

declare(strict_types=1);

namespace AuditTrail\Application\Service;

use AuditTrail\Application\DTO\ChangeRequest;
use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Domain\Exception\InvalidStateTransitionException;
use AuditTrail\Port\AuditRepository;
use AuditTrail\Port\IdGenerator;
use Psr\Log\LoggerInterface;

final class AuditService
{
    public function __construct(
        private readonly AuditRepository $repository,
        private readonly LoggerInterface $logger,
        private readonly IdGenerator $idGenerator,
    ) {
    }

    /**
     * UC-1: Record an audit entry for an entity change.
     *
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     * @param array<string, mixed>|null $metadata
     */
    public function recordChange(
        string $aggregateType,
        string $aggregateId,
        string $action,
        ?array $oldState,
        ?array $newState,
        string $performedBy,
        ?array $metadata = null,
    ): AuditEntry {
        $this->validateTransition($action, $oldState, $newState);

        $entry = AuditEntry::record(
            id: $this->generateId(),
            aggregateType: $aggregateType,
            aggregateId: $aggregateId,
            action: $action,
            oldState: $oldState,
            newState: $newState,
            performedBy: $performedBy,
            metadata: $metadata,
        );

        $this->repository->append($entry);

        $this->logger->info('Audit entry recorded', [
            'id' => $entry->id(),
            'action' => $entry->action()->value,
            'aggregate_type' => $entry->aggregateType(),
            'aggregate_id' => $entry->aggregateId(),
            'performed_by' => $entry->performedBy(),
        ]);

        return $entry;
    }

    /**
     * UC-3: Get full revision history for an entity.
     *
     * @return list<AuditEntry>
     */
    public function getHistory(string $aggregateType, string $aggregateId): array
    {
        $entries = $this->repository->findByAggregate($aggregateType, $aggregateId);

        $this->logger->info('Audit history retrieved', [
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'count' => count($entries),
        ]);

        return $entries;
    }

    /**
     * UC-4: Get a specific audit entry by ID.
     */
    public function getEntry(string $id): AuditEntry
    {
        $entry = $this->repository->findById($id);

        $this->logger->info('Audit entry retrieved', [
            'id' => $entry->id(),
        ]);

        return $entry;
    }

    /**
     * UC-2: Get all entries performed by a user.
     *
     * @return list<AuditEntry>
     */
    public function getEntriesByUser(string $userId): array
    {
        $entries = $this->repository->findByPerformedBy($userId);

        $this->logger->info('Audit entries by user retrieved', [
            'user_id' => $userId,
            'count' => count($entries),
        ]);

        return $entries;
    }

    /**
     * UC-2: Get all entries within a date range.
     *
     * @return list<AuditEntry>
     */
    public function getEntriesByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = $this->repository->findByDateRange($from, $to);

        $this->logger->info('Audit entries by date range retrieved', [
            'from' => $from->format('Y-m-d\TH:i:s.uP'),
            'to' => $to->format('Y-m-d\TH:i:s.uP'),
            'count' => count($entries),
        ]);

        return $entries;
    }

    public function countByAggregate(string $aggregateType, string $aggregateId): int
    {
        $count = $this->repository->countByAggregate($aggregateType, $aggregateId);

        $this->logger->info('Audit entries counted', [
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * @return list<AuditEntry>
     */
    public function getEntriesByAction(Action $action): array
    {
        $entries = $this->repository->findByAction($action);

        $this->logger->info('Audit entries by action retrieved', [
            'action' => $action->value,
            'count' => count($entries),
        ]);

        return $entries;
    }

    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     */
    private function validateTransition(string $action, ?array $oldState, ?array $newState): void
    {
        $resolved = Action::tryFrom(strtoupper(trim($action)));

        if ($resolved === null) {
            throw new InvalidStateTransitionException($action, 'Action does not exist');
        }

        match ($resolved) {
            Action::CREATE => $this->validateCreateTransition($oldState, $newState),
            Action::UPDATE => $this->validateUpdateTransition($oldState, $newState),
            Action::DELETE => $this->validateDeleteTransition($oldState, $newState),
        };
    }

    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     */
    private function validateCreateTransition(?array $oldState, ?array $newState): void
    {
        if ($oldState !== null) {
            throw new InvalidStateTransitionException('CREATE', 'oldState must be null on create');
        }

        if ($newState === null) {
            throw new InvalidStateTransitionException('CREATE', 'newState must not be null on create');
        }
    }

    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     */
    private function validateUpdateTransition(?array $oldState, ?array $newState): void
    {
        if ($oldState === null) {
            throw new InvalidStateTransitionException('UPDATE', 'oldState must not be null on update');
        }

        if ($newState === null) {
            throw new InvalidStateTransitionException('UPDATE', 'newState must not be null on update');
        }
    }

    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     */
    private function validateDeleteTransition(?array $oldState, ?array $newState): void
    {
        if ($oldState === null) {
            throw new InvalidStateTransitionException('DELETE', 'oldState must not be null on delete');
        }

        if ($newState !== null) {
            throw new InvalidStateTransitionException('DELETE', 'newState must be null on delete');
        }
    }

    /**
     * Record multiple audit entries atomically (all-or-nothing).
     *
     * Validates every transition before persisting any.
     *
     * @param list<ChangeRequest> $changes
     *
     * @return list<AuditEntry>
     */
    public function recordBatch(array $changes): array
    {
        if ($changes === []) {
            $this->logger->info('Audit batch recorded', ['count' => 0]);
            return [];
        }

        $entries = [];

        foreach ($changes as $change) {
            $this->validateTransition($change->action, $change->oldState, $change->newState);

            $entries[] = AuditEntry::record(
                id: $this->generateId(),
                aggregateType: $change->aggregateType,
                aggregateId: $change->aggregateId,
                action: $change->action,
                oldState: $change->oldState,
                newState: $change->newState,
                performedBy: $change->performedBy,
                metadata: $change->metadata,
            );
        }

        $this->repository->appendBatch($entries);

        $this->logger->info('Audit batch recorded', ['count' => count($entries)]);

        return $entries;
    }

    private function generateId(): string
    {
        return $this->idGenerator->generate();
    }
}
