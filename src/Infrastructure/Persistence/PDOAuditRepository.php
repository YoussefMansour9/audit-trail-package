<?php

declare(strict_types=1);

namespace AuditTrail\Infrastructure\Persistence;

use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Domain\Exception\AuditTrailException;
use AuditTrail\Domain\Exception\EntryNotFoundException;
use AuditTrail\Port\AuditRepository;

final class PDOAuditRepository implements AuditRepository
{
    private const TABLE = 'audit_log';

    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function append(AuditEntry $entry): void
    {
        $data = $entry->toArray();

        $sql = sprintf(
            'INSERT INTO %s (id, aggregate_type, aggregate_id, action, old_state, new_state, performed_by, performed_at, metadata) '
            . 'VALUES (:id, :aggregate_type, :aggregate_id, :action, :old_state, :new_state, :performed_by, :performed_at, :metadata)',
            self::TABLE,
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id' => $data['id'],
                ':aggregate_type' => $data['aggregate_type'],
                ':aggregate_id' => $data['aggregate_id'],
                ':action' => $data['action'],
                ':old_state' => $data['old_state'] !== null ? json_encode($data['old_state'], JSON_THROW_ON_ERROR) : null,
                ':new_state' => $data['new_state'] !== null ? json_encode($data['new_state'], JSON_THROW_ON_ERROR) : null,
                ':performed_by' => $data['performed_by'],
                ':performed_at' => $data['performed_at'],
                ':metadata' => $data['metadata'] !== null ? json_encode($data['metadata'], JSON_THROW_ON_ERROR) : null,
            ]);
        } catch (\PDOException $e) {
            throw new AuditTrailException(
                sprintf('Failed to append audit entry: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    public function appendBatch(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        try {
            $this->pdo->beginTransaction();

            foreach ($entries as $entry) {
                $this->append($entry);
            }

            $this->pdo->commit();
        } catch (\Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findById(string $id): AuditEntry
    {
        $sql = sprintf('SELECT * FROM %s WHERE id = :id', self::TABLE);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            /** @var array<string, mixed>|false $row */
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new AuditTrailException(
                sprintf('Failed to find audit entry: %s', $e->getMessage()),
                previous: $e,
            );
        }

        if ($row === false || $row === []) {
            throw new EntryNotFoundException($id);
        }

        return $this->hydrate($row);
    }

    public function findByAggregate(string $aggregateType, string $aggregateId): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_type = :aggregate_type AND aggregate_id = :aggregate_id ORDER BY performed_at ASC',
            self::TABLE,
        );

        return $this->fetchAll($sql, [
            ':aggregate_type' => $aggregateType,
            ':aggregate_id' => $aggregateId,
        ]);
    }

    public function findByAggregatePaginated(
        string $aggregateType,
        string $aggregateId,
        int $limit,
        int $offset = 0,
    ): array {
        $sql = sprintf(
            'SELECT * FROM %s WHERE aggregate_type = :aggregate_type AND aggregate_id = :aggregate_id ORDER BY performed_at ASC LIMIT :limit OFFSET :offset',
            self::TABLE,
        );

        return $this->fetchAll($sql, [
            ':aggregate_type' => $aggregateType,
            ':aggregate_id' => $aggregateId,
            ':limit' => $limit,
            ':offset' => $offset,
        ]);
    }

    public function findByAction(Action $action): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE action = :action ORDER BY performed_at DESC',
            self::TABLE,
        );

        return $this->fetchAll($sql, [':action' => $action->value]);
    }

    public function findByPerformedBy(string $userId): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE performed_by = :performed_by ORDER BY performed_at DESC',
            self::TABLE,
        );

        return $this->fetchAll($sql, [':performed_by' => $userId]);
    }

    public function findByDateRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $sql = sprintf(
            'SELECT * FROM %s WHERE performed_at >= :from_date AND performed_at <= :to_date ORDER BY performed_at DESC',
            self::TABLE,
        );

        return $this->fetchAll($sql, [
            ':from_date' => $from->format('Y-m-d\TH:i:s.uP'),
            ':to_date' => $to->format('Y-m-d\TH:i:s.uP'),
        ]);
    }

    public function countByAggregate(string $aggregateType, string $aggregateId): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM %s WHERE aggregate_type = :aggregate_type AND aggregate_id = :aggregate_id',
            self::TABLE,
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':aggregate_type' => $aggregateType,
                ':aggregate_id' => $aggregateId,
            ]);

            return (int) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            throw new AuditTrailException(
                sprintf('Failed to count audit entries: %s', $e->getMessage()),
                previous: $e,
            );
        }
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return list<AuditEntry>
     */
    private function fetchAll(string $sql, array $params): array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new AuditTrailException(
                sprintf('Failed to query audit entries: %s', $e->getMessage()),
                previous: $e,
            );
        }

        $entries = [];
        foreach ($rows as $row) {
            $entries[] = $this->hydrate($row);
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): AuditEntry
    {
        return AuditEntry::fromArray([
            'id' => $this->ensureString($row['id']),
            'aggregate_type' => $this->ensureString($row['aggregate_type']),
            'aggregate_id' => $this->ensureString($row['aggregate_id']),
            'action' => $this->ensureString($row['action']),
            'old_state' => $this->decodeJson($row['old_state'] ?? null),
            'new_state' => $this->decodeJson($row['new_state'] ?? null),
            'performed_by' => $this->ensureString($row['performed_by']),
            'performed_at' => $this->ensureString($row['performed_at']),
            'metadata' => $this->decodeJson($row['metadata'] ?? null),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $value): array|null
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function ensureString(mixed $value): string
    {
        assert(is_string($value));

        return $value;
    }
}
