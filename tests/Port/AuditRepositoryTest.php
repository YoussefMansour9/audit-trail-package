<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Port;

use AuditTrail\Port\AuditRepository;
use AuditTrail\Domain\AuditEntry;
use PHPUnit\Framework\TestCase;

final class AuditRepositoryTest extends TestCase
{
    public function test_interface_contract(): void
    {
        $repo = $this->createMock(AuditRepository::class);
        $entry = AuditEntry::record(
            id: 'test-id',
            aggregateType: 'order',
            aggregateId: '1',
            action: 'CREATE',
            oldState: null,
            newState: [],
            performedBy: 'user',
        );

        $repo->expects($this->once())->method('append')->with($entry);
        $repo->append($entry);

        $repo->method('findById')->willReturn($entry);
        $result = $repo->findById('test-id');
        $this->assertSame($entry, $result);

        $repo->method('findByAggregate')->willReturn([$entry]);
        $result = $repo->findByAggregate('order', '1');
        $this->assertCount(1, $result);
        $this->assertSame($entry, $result[0]);

        $repo->method('findByDateRange')->willReturn([$entry]);
        $from = new \DateTimeImmutable('-1 day');
        $to = new \DateTimeImmutable('+1 day');
        $result = $repo->findByDateRange($from, $to);
        $this->assertCount(1, $result);
        $this->assertSame($entry, $result[0]);
    }
}
