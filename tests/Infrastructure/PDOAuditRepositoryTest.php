<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Infrastructure;

use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Domain\Exception\EntryNotFoundException;
use AuditTrail\Infrastructure\Persistence\PDOAuditRepository;
use PHPUnit\Framework\TestCase;

final class PDOAuditRepositoryTest extends TestCase
{
    private \PDO $pdo;
    private PDOAuditRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS audit_log (
                id              TEXT    NOT NULL PRIMARY KEY,
                aggregate_type  TEXT    NOT NULL,
                aggregate_id    TEXT    NOT NULL,
                action          TEXT    NOT NULL,
                old_state       TEXT    DEFAULT NULL,
                new_state       TEXT    DEFAULT NULL,
                performed_by    TEXT    NOT NULL,
                performed_at    TEXT    NOT NULL,
                metadata        TEXT    DEFAULT NULL
            )
        ');

        $this->repository = new PDOAuditRepository($this->pdo);
    }

    // ─── append + findById ───────────────────────────────────────

    public function test_append_and_find_by_id(): void
    {
        $entry = AuditEntry::record(
            id: 'id-1',
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-a',
        );

        $this->repository->append($entry);
        $found = $this->repository->findById('id-1');

        $this->assertSame('id-1', $found->id());
        $this->assertSame('order', $found->aggregateType());
        $this->assertSame('42', $found->aggregateId());
        $this->assertSame(Action::CREATE, $found->action());
        $this->assertNull($found->oldState());
        $this->assertSame(['status' => 'pending'], $found->newState());
        $this->assertSame('user-a', $found->performedBy());
        $this->assertInstanceOf(\DateTimeImmutable::class, $found->performedAt());
        $this->assertNull($found->metadata());
    }

    public function test_find_by_id_not_found_throws(): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->repository->findById('non-existent');
    }

    public function test_append_with_full_data_and_metadata(): void
    {
        $entry = AuditEntry::record(
            id: 'id-2',
            aggregateType: 'user',
            aggregateId: '10',
            action: 'UPDATE',
            oldState: ['name' => 'Alice', 'email' => 'alice@old.com'],
            newState: ['name' => 'Alice', 'email' => 'alice@new.com'],
            performedBy: 'admin',
            metadata: ['ip' => '127.0.0.1', 'reason' => 'email change'],
        );

        $this->repository->append($entry);
        $found = $this->repository->findById('id-2');

        $this->assertSame('id-2', $found->id());
        $this->assertSame(['name' => 'Alice', 'email' => 'alice@old.com'], $found->oldState());
        $this->assertSame(['name' => 'Alice', 'email' => 'alice@new.com'], $found->newState());
        $this->assertSame(['ip' => '127.0.0.1', 'reason' => 'email change'], $found->metadata());
    }

    public function test_append_with_delete_action(): void
    {
        $entry = AuditEntry::record(
            id: 'id-3',
            aggregateType: 'order',
            aggregateId: '99',
            action: 'DELETE',
            oldState: ['status' => 'shipped'],
            newState: null,
            performedBy: 'user-b',
        );

        $this->repository->append($entry);
        $found = $this->repository->findById('id-3');

        $this->assertSame(Action::DELETE, $found->action());
        $this->assertSame(['status' => 'shipped'], $found->oldState());
        $this->assertNull($found->newState());
    }

    // ─── findByAggregate ─────────────────────────────────────────

    public function test_find_by_aggregate_returns_entries_ordered_oldest_first(): void
    {
        $entry1 = AuditEntry::record(
            id: 'a1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: ['s' => 'a'],
            performedBy: 'u',
        );
        $entry2 = AuditEntry::record(
            id: 'a2', aggregateType: 'order', aggregateId: '1',
            action: 'UPDATE', oldState: ['s' => 'a'], newState: ['s' => 'b'],
            performedBy: 'u',
        );

        $this->repository->append($entry1);
        usleep(2000);
        $this->repository->append($entry2);

        $entries = $this->repository->findByAggregate('order', '1');

        $this->assertCount(2, $entries);
        $this->assertSame('a1', $entries[0]->id());
        $this->assertSame('a2', $entries[1]->id());
    }

    public function test_find_by_aggregate_returns_empty_array_when_none(): void
    {
        $entries = $this->repository->findByAggregate('order', '999');

        $this->assertSame([], $entries);
    }

    // ─── findByPerformedBy ───────────────────────────────────────

    public function test_find_by_performed_by_returns_newest_first(): void
    {
        $e1 = AuditEntry::record(
            id: 'u1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'bob',
        );
        $e2 = AuditEntry::record(
            id: 'u2', aggregateType: 'order', aggregateId: '2',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'bob',
        );

        $this->repository->append($e1);
        usleep(2000);
        $this->repository->append($e2);

        $entries = $this->repository->findByPerformedBy('bob');

        $this->assertCount(2, $entries);
        $this->assertSame('u2', $entries[0]->id());
        $this->assertSame('u1', $entries[1]->id());
    }

    public function test_find_by_performed_by_returns_empty_array_when_none(): void
    {
        $entries = $this->repository->findByPerformedBy('nobody');

        $this->assertSame([], $entries);
    }

    // ─── findByDateRange ─────────────────────────────────────────

    public function test_find_by_date_range_returns_matching_entries(): void
    {
        $e1 = AuditEntry::record(
            id: 'd1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        );

        $this->repository->append($e1);

        $from = new \DateTimeImmutable('-1 hour');
        $to = new \DateTimeImmutable('+1 hour');

        $entries = $this->repository->findByDateRange($from, $to);

        $this->assertCount(1, $entries);
    }

    public function test_find_by_date_range_excludes_outside_range(): void
    {
        $past = AuditEntry::record(
            id: 'old-1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        );

        $this->repository->append($past);

        $from = new \DateTimeImmutable('+1 day');
        $to = new \DateTimeImmutable('+2 days');

        $entries = $this->repository->findByDateRange($from, $to);

        $this->assertSame([], $entries);
    }

    // ─── countByAggregate ────────────────────────────────────────

    public function test_count_by_aggregate_returns_zero_when_none(): void
    {
        $count = $this->repository->countByAggregate('order', '999');

        $this->assertSame(0, $count);
    }

    public function test_count_by_aggregate_returns_correct_count(): void
    {
        $this->repository->append(AuditEntry::record(
            id: 'c1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        ));
        $this->repository->append(AuditEntry::record(
            id: 'c2', aggregateType: 'order', aggregateId: '1',
            action: 'UPDATE', oldState: ['a' => 1], newState: ['a' => 2],
            performedBy: 'u',
        ));

        $count = $this->repository->countByAggregate('order', '1');
        $this->assertSame(2, $count);
    }

    public function test_count_by_aggregate_does_not_count_other_aggregates(): void
    {
        $this->repository->append(AuditEntry::record(
            id: 'c3', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        ));
        $this->repository->append(AuditEntry::record(
            id: 'c4', aggregateType: 'user', aggregateId: '10',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        ));

        $this->assertSame(1, $this->repository->countByAggregate('order', '1'));
        $this->assertSame(1, $this->repository->countByAggregate('user', '10'));
    }

    // ─── persistence of timestamp precision ──────────────────────

    public function test_performed_at_is_preserved_exactly(): void
    {
        $entry = AuditEntry::record(
            id: 'ts-1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        );

        $this->repository->append($entry);
        $found = $this->repository->findById('ts-1');

        $this->assertSame(
            $entry->performedAt()->format('Y-m-d\TH:i:s.uP'),
            $found->performedAt()->format('Y-m-d\TH:i:s.uP'),
        );
    }
}
