<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Application;

use AuditTrail\Application\Exception\InvalidStateTransitionException;
use AuditTrail\Application\Service\AuditService;
use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Domain\Exception\EntryNotFoundException;
use AuditTrail\Port\AuditRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuditServiceTest extends TestCase
{
    private AuditRepository $repository;
    private LoggerInterface $logger;
    private AuditService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AuditRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new AuditService($this->repository, $this->logger);
    }

    // ─── recordChange: success paths ─────────────────────────────

    public function test_record_create_success(): void
    {
        $this->repository->expects($this->once())->method('append');
        $this->logger->expects($this->once())->method('info');

        $entry = $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );

        $this->assertSame('order', $entry->aggregateType());
        $this->assertSame('42', $entry->aggregateId());
        $this->assertSame(Action::CREATE, $entry->action());
        $this->assertNull($entry->oldState());
        $this->assertSame(['status' => 'pending'], $entry->newState());
        $this->assertSame('user-abc', $entry->performedBy());
    }

    public function test_record_update_success(): void
    {
        $this->repository->expects($this->once())->method('append');

        $entry = $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'UPDATE',
            oldState: ['status' => 'pending'],
            newState: ['status' => 'shipped'],
            performedBy: 'user-abc',
        );

        $this->assertSame(Action::UPDATE, $entry->action());
        $this->assertSame(['status' => 'pending'], $entry->oldState());
        $this->assertSame(['status' => 'shipped'], $entry->newState());
    }

    public function test_record_delete_success(): void
    {
        $this->repository->expects($this->once())->method('append');

        $entry = $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'DELETE',
            oldState: ['status' => 'shipped'],
            newState: null,
            performedBy: 'user-abc',
        );

        $this->assertSame(Action::DELETE, $entry->action());
        $this->assertSame(['status' => 'shipped'], $entry->oldState());
        $this->assertNull($entry->newState());
    }

    // ─── recordChange: validation failures ───────────────────────

    public function test_record_create_with_old_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('oldState must be null on create');

        $this->repository->expects($this->never())->method('append');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: ['existing' => 'data'],
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );
    }

    public function test_record_create_without_new_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('newState must not be null on create');

        $this->repository->expects($this->never())->method('append');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: null,
            performedBy: 'user-abc',
        );
    }

    public function test_record_update_without_old_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('oldState must not be null on update');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'UPDATE',
            oldState: null,
            newState: ['status' => 'shipped'],
            performedBy: 'user-abc',
        );
    }

    public function test_record_update_without_new_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('newState must not be null on update');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'UPDATE',
            oldState: ['status' => 'pending'],
            newState: null,
            performedBy: 'user-abc',
        );
    }

    public function test_record_delete_without_old_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('oldState must not be null on delete');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'DELETE',
            oldState: null,
            newState: null,
            performedBy: 'user-abc',
        );
    }

    public function test_record_delete_with_new_state_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessage('newState must be null on delete');

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'DELETE',
            oldState: ['status' => 'shipped'],
            newState: ['status' => 'archived'],
            performedBy: 'user-abc',
        );
    }

    public function test_record_invalid_action_throws(): void
    {
        $this->expectException(InvalidStateTransitionException::class);

        $this->service->recordChange(
            aggregateType: 'order',
            aggregateId: '42',
            action: 'NONSENSE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );
    }

    // ─── recordChange: generates unique IDs ──────────────────────

    public function test_record_generates_unique_ids(): void
    {
        $this->repository->method('append');

        $entry1 = $this->service->recordChange(
            aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: ['x' => 1],
            performedBy: 'u',
        );
        $entry2 = $this->service->recordChange(
            aggregateType: 'order', aggregateId: '2',
            action: 'CREATE', oldState: null, newState: ['x' => 2],
            performedBy: 'u',
        );

        $this->assertNotSame($entry1->id(), $entry2->id());
    }

    // ─── query methods ───────────────────────────────────────────

    public function test_get_history_delegates_to_repository(): void
    {
        $expected = [AuditEntry::record(
            id: 'x', aggregateType: 'order', aggregateId: '1',
            action: 'UPDATE', oldState: ['a' => 1], newState: ['a' => 2],
            performedBy: 'u',
        )];

        $this->repository->method('findByAggregate')
            ->with('order', '1')
            ->willReturn($expected);
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->getHistory('order', '1');

        $this->assertSame($expected, $result);
    }

    public function test_get_entry_delegates_to_repository(): void
    {
        $expected = AuditEntry::record(
            id: 'abc-123', aggregateType: 'order', aggregateId: '1',
            action: 'CREATE', oldState: null, newState: [],
            performedBy: 'u',
        );

        $this->repository->method('findById')->with('abc-123')->willReturn($expected);
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->getEntry('abc-123');

        $this->assertSame($expected, $result);
    }

    public function test_get_entry_not_found_propagates_exception(): void
    {
        $this->repository->method('findById')
            ->willThrowException(new EntryNotFoundException('missing'));

        $this->expectException(EntryNotFoundException::class);

        $this->service->getEntry('missing');
    }

    public function test_get_entries_by_user_delegates_to_repository(): void
    {
        $this->repository->method('findByPerformedBy')->with('user-x')->willReturn([]);
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->getEntriesByUser('user-x');

        $this->assertSame([], $result);
    }

    public function test_get_entries_by_date_range_delegates_to_repository(): void
    {
        $from = new \DateTimeImmutable('-7 days');
        $to = new \DateTimeImmutable('now');

        $this->repository->method('findByDateRange')->with($from, $to)->willReturn([]);
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->getEntriesByDateRange($from, $to);

        $this->assertSame([], $result);
    }

    // ─── recordBatch ─────────────────────────────────────────────

    public function test_record_batch_empty_returns_empty_array(): void
    {
        $this->repository->expects($this->never())->method('append');
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->recordBatch([]);

        $this->assertSame([], $result);
    }

    public function test_record_batch_success(): void
    {
        $this->repository->expects($this->exactly(3))->method('append');
        $this->logger->expects($this->once())->method('info');

        $result = $this->service->recordBatch([
            ['order', '1', 'CREATE', null, ['status' => 'pending'], 'user-a'],
            ['order', '1', 'UPDATE', ['status' => 'pending'], ['status' => 'shipped'], 'user-a'],
            ['order', '1', 'DELETE', ['status' => 'shipped'], null, 'user-a'],
        ]);

        $this->assertCount(3, $result);
        $this->assertSame(Action::CREATE, $result[0]->action());
        $this->assertSame(Action::UPDATE, $result[1]->action());
        $this->assertSame(Action::DELETE, $result[2]->action());
    }

    public function test_record_batch_all_or_nothing_on_validation_failure(): void
    {
        $this->repository->expects($this->never())->method('append');
        $this->logger->expects($this->never())->method('info');

        $this->expectException(InvalidStateTransitionException::class);

        $this->service->recordBatch([
            ['order', '1', 'CREATE', null, ['status' => 'pending'], 'user-a'],
            ['order', '1', 'UPDATE', null, null, 'user-a'],
            ['order', '1', 'DELETE', ['status' => 'shipped'], null, 'user-a'],
        ]);
    }

    public function test_record_batch_with_metadata(): void
    {
        $this->repository->expects($this->once())->method('append');

        $result = $this->service->recordBatch([
            ['order', '1', 'CREATE', null, ['status' => 'pending'], 'user-a', ['ip' => '127.0.0.1']],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(['ip' => '127.0.0.1'], $result[0]->metadata());
    }
}
