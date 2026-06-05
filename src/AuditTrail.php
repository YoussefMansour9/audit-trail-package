<?php

declare(strict_types=1);

namespace AuditTrail;

use AuditTrail\Application\Service\AuditService;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Infrastructure\Persistence\PDOAuditRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class AuditTrail
{
    public function __construct(
        private readonly AuditService $service,
    ) {
    }

    /**
     * Create an AuditTrail instance with a PDO backend and optional logger.
     */
    public static function createWithPdo(
        \PDO $pdo,
        ?LoggerInterface $logger = null,
    ): self {
        $repository = new PDOAuditRepository($pdo);
        $service = new AuditService($repository, $logger ?? new NullLogger());

        return new self($service);
    }

    /**
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
        return $this->service->recordChange(
            $aggregateType,
            $aggregateId,
            $action,
            $oldState,
            $newState,
            $performedBy,
            $metadata,
        );
    }

    /**
     * @param list<array{
     *     0: string,
     *     1: string,
     *     2: string,
     *     3: array<string, mixed>|null,
     *     4: array<string, mixed>|null,
     *     5: string,
     *     6?: array<string, mixed>|null,
     * }> $changes
     *
     * @return list<AuditEntry>
     */
    public function recordBatch(array $changes): array
    {
        return $this->service->recordBatch($changes);
    }

    /**
     * @return list<AuditEntry>
     */
    public function getHistory(string $aggregateType, string $aggregateId): array
    {
        return $this->service->getHistory($aggregateType, $aggregateId);
    }

    public function getEntry(string $id): AuditEntry
    {
        return $this->service->getEntry($id);
    }

    /**
     * @return list<AuditEntry>
     */
    public function getEntriesByUser(string $userId): array
    {
        return $this->service->getEntriesByUser($userId);
    }

    /**
     * @return list<AuditEntry>
     */
    public function getEntriesByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->service->getEntriesByDateRange($from, $to);
    }

    public function countByAggregate(string $aggregateType, string $aggregateId): int
    {
        return $this->service->countByAggregate($aggregateType, $aggregateId);
    }
}
