<?php

declare(strict_types=1);

namespace AuditTrail\Port;

use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;

interface AuditRepository
{
    /**
     * Persist a new audit entry.
     *
     * @throws \AuditTrail\Domain\Exception\AuditTrailException on storage failure
     */
    public function append(AuditEntry $entry): void;

    /**
     * Persist multiple entries atomically (all-or-nothing).
     *
     * If the storage supports transactions, all entries are written
     * within a single transaction. If any entry fails, none are written.
     *
     * @param list<AuditEntry> $entries
     *
     * @throws \AuditTrail\Domain\Exception\AuditTrailException on storage failure
     */
    public function appendBatch(array $entries): void;

    /**
     * Find a single entry by its unique identifier.
     *
     * @throws \AuditTrail\Domain\Exception\EntryNotFoundException when not found
     */
    public function findById(string $id): AuditEntry;

    /**
     * Find all entries for a given aggregate, ordered oldest-first.
     *
     * @return list<AuditEntry>
     */
    public function findByAggregate(string $aggregateType, string $aggregateId): array;

    /**
     * Find entries for a given aggregate with pagination, ordered oldest-first.
     *
     * @return list<AuditEntry>
     */
    public function findByAggregatePaginated(
        string $aggregateType,
        string $aggregateId,
        int $limit,
        int $offset = 0,
    ): array;

    /**
     * Find all entries with a given action, ordered newest-first.
     *
     * @return list<AuditEntry>
     */
    public function findByAction(Action $action): array;

    /**
     * Find all entries performed by a specific user, ordered newest-first.
     *
     * @return list<AuditEntry>
     */
    public function findByPerformedBy(string $userId): array;

    /**
     * Find all entries within a date range, ordered newest-first.
     *
     * @param \DateTimeImmutable $from Inclusive start
     * @param \DateTimeImmutable $to   Inclusive end
     *
     * @return list<AuditEntry>
     */
    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * Count all entries for a given aggregate.
     */
    public function countByAggregate(string $aggregateType, string $aggregateId): int;
}
