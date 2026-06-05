<?php

declare(strict_types=1);

namespace AuditTrail\Tests;

use AuditTrail\Application\Service\AuditService;
use AuditTrail\AuditTrail;
use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Port\AuditRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AuditTrailTest extends TestCase
{
    private AuditRepository $repository;
    private AuditTrail $auditTrail;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditRepository::class);
        $service = new AuditService($this->repository, new NullLogger());
        $this->auditTrail = new AuditTrail($service);
    }

    public function test_record_change_delegates(): void
    {
        $this->repository->expects($this->once())->method('append');

        $entry = $this->auditTrail->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-a',
        );

        $this->assertSame(Action::CREATE, $entry->action());
    }

    public function test_get_history_delegates(): void
    {
        $this->repository->method('findByAggregate')->willReturn([]);

        $result = $this->auditTrail->getHistory('order', '42');

        $this->assertSame([], $result);
    }

    public function test_get_entry_delegates(): void
    {
        $entry = AuditEntry::record(
            id: 'e1', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        );
        $this->repository->method('findById')->with('e1')->willReturn($entry);

        $result = $this->auditTrail->getEntry('e1');

        $this->assertSame('e1', $result->id());
    }

    public function test_record_batch_delegates(): void
    {
        $this->repository->expects($this->exactly(2))->method('append');

        $result = $this->auditTrail->recordBatch([
            ['order', '1', 'CREATE', null, ['s' => 'a'], 'u'],
            ['order', '1', 'UPDATE', ['s' => 'a'], ['s' => 'b'], 'u'],
        ]);

        $this->assertCount(2, $result);
    }

    public function test_get_entries_by_user_delegates(): void
    {
        $this->repository->method('findByPerformedBy')->willReturn([]);

        $result = $this->auditTrail->getEntriesByUser('alice');

        $this->assertSame([], $result);
    }

    public function test_get_entries_by_date_range_delegates(): void
    {
        $this->repository->method('findByDateRange')->willReturn([]);

        $result = $this->auditTrail->getEntriesByDateRange(
            new \DateTimeImmutable('-1 day'),
            new \DateTimeImmutable('now'),
        );

        $this->assertSame([], $result);
    }

    public function test_count_by_aggregate_delegates(): void
    {
        $this->repository->method('countByAggregate')->with('order', '1')->willReturn(3);

        $result = $this->auditTrail->countByAggregate('order', '1');

        $this->assertSame(3, $result);
    }
}
