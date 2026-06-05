<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Domain;

use AuditTrail\Domain\Action;
use AuditTrail\Domain\AuditEntry;
use AuditTrail\Domain\Exception\InvalidActionException;
use PHPUnit\Framework\TestCase;

final class AuditEntryTest extends TestCase
{
    private const UUID = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';

    public function test_record_creates_audit_entry(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending', 'total' => 99.99],
            performedBy: 'user-abc',
        );

        $this->assertSame(self::UUID, $entry->id());
        $this->assertSame('order', $entry->aggregateType());
        $this->assertSame('42', $entry->aggregateId());
        $this->assertTrue($entry->action() === Action::CREATE);
        $this->assertNull($entry->oldState());
        $this->assertSame(['status' => 'pending', 'total' => 99.99], $entry->newState());
        $this->assertSame('user-abc', $entry->performedBy());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->performedAt());
        $this->assertNull($entry->metadata());
    }

    public function test_record_with_metadata(): void
    {
        $metadata = ['ip' => '127.0.0.1', 'request_id' => 'req-123'];

        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'user',
            aggregateId: '10',
            action: 'UPDATE',
            oldState: ['name' => 'Alice'],
            newState: ['name' => 'Alice Updated'],
            performedBy: 'admin',
            metadata: $metadata,
        );

        $this->assertSame($metadata, $entry->metadata());
    }

    public function test_update_action_has_both_states(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'UPDATE',
            oldState: ['status' => 'pending'],
            newState: ['status' => 'shipped'],
            performedBy: 'user-abc',
        );

        $this->assertNotNull($entry->oldState());
        $this->assertNotNull($entry->newState());
    }

    public function test_delete_action_has_no_new_state(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'DELETE',
            oldState: ['status' => 'shipped'],
            newState: null,
            performedBy: 'user-abc',
        );

        $this->assertNotNull($entry->oldState());
        $this->assertNull($entry->newState());
    }

    public function test_record_throws_for_invalid_action(): void
    {
        $this->expectException(InvalidActionException::class);
        $this->expectExceptionMessage('Invalid audit action: "INVALID"');

        AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'INVALID',
            oldState: null,
            newState: null,
            performedBy: 'user-abc',
        );
    }

    public function test_record_action_is_case_insensitive(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'create',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );

        $this->assertSame(Action::CREATE, $entry->action());
    }

    public function test_record_action_trims_whitespace(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: '  UPDATE  ',
            oldState: ['old' => 1],
            newState: ['new' => 2],
            performedBy: 'user-abc',
        );

        $this->assertSame(Action::UPDATE, $entry->action());
    }

    public function test_record_performed_at_is_immutable_datetime(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->performedAt());
    }

    public function test_performed_at_is_set_at_record_time(): void
    {
        $before = new \DateTimeImmutable();
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $entry->performedAt());
        $this->assertLessThanOrEqual($after, $entry->performedAt());
    }

    public function test_to_array_contains_all_fields(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
            metadata: ['ip' => '127.0.0.1'],
        );

        $array = $entry->toArray();

        $this->assertSame(self::UUID, $array['id']);
        $this->assertSame('order', $array['aggregate_type']);
        $this->assertSame('42', $array['aggregate_id']);
        $this->assertSame('CREATE', $array['action']);
        $this->assertNull($array['old_state']);
        $this->assertSame(['status' => 'pending'], $array['new_state']);
        $this->assertSame('user-abc', $array['performed_by']);
        $this->assertIsString($array['performed_at']);
        $this->assertSame(['ip' => '127.0.0.1'], $array['metadata']);
    }

    public function test_to_array_performed_at_is_iso8601(): void
    {
        $entry = AuditEntry::record(
            id: self::UUID,
            aggregateType: 'order',
            aggregateId: '42',
            action: 'CREATE',
            oldState: null,
            newState: ['status' => 'pending'],
            performedBy: 'user-abc',
        );

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}/',
            $entry->toArray()['performed_at'],
        );
    }

    // ─── fromArray tests ─────────────────────────────────────────

    public function test_fromArray_reconstructs_entry(): void
    {
        $data = [
            'id' => self::UUID,
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'action' => 'UPDATE',
            'old_state' => ['status' => 'pending'],
            'new_state' => ['status' => 'shipped'],
            'performed_by' => 'user-abc',
            'performed_at' => '2026-06-06T12:00:00.000000+00:00',
            'metadata' => ['ip' => '127.0.0.1'],
        ];

        $entry = AuditEntry::fromArray($data);

        $this->assertSame(self::UUID, $entry->id());
        $this->assertSame('order', $entry->aggregateType());
        $this->assertSame('42', $entry->aggregateId());
        $this->assertSame(Action::UPDATE, $entry->action());
        $this->assertSame(['status' => 'pending'], $entry->oldState());
        $this->assertSame(['status' => 'shipped'], $entry->newState());
        $this->assertSame('user-abc', $entry->performedBy());
        $this->assertSame('user-abc', $entry->performedBy());
        $this->assertEquals(
            new \DateTimeImmutable('2026-06-06T12:00:00.000000+00:00'),
            $entry->performedAt(),
        );
        $this->assertSame(['ip' => '127.0.0.1'], $entry->metadata());
    }

    public function test_fromArray_with_null_states(): void
    {
        $data = [
            'id' => self::UUID,
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'action' => 'CREATE',
            'old_state' => null,
            'new_state' => ['status' => 'pending'],
            'performed_by' => 'user-abc',
            'performed_at' => '2026-06-06T12:00:00.000000+00:00',
        ];

        $entry = AuditEntry::fromArray($data);

        $this->assertNull($entry->oldState());
        $this->assertSame(['status' => 'pending'], $entry->newState());
        $this->assertNull($entry->metadata());
    }

    public function test_fromArray_missing_id_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "id"');

        AuditEntry::fromArray([
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'action' => 'CREATE',
            'performed_by' => 'user-abc',
            'performed_at' => '2026-06-06T12:00:00.000000+00:00',
        ]);
    }

    public function test_fromArray_missing_action_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "action"');

        AuditEntry::fromArray([
            'id' => self::UUID,
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'performed_by' => 'user-abc',
            'performed_at' => '2026-06-06T12:00:00.000000+00:00',
        ]);
    }

    public function test_fromArray_invalid_action_throws(): void
    {
        $this->expectException(InvalidActionException::class);

        AuditEntry::fromArray([
            'id' => self::UUID,
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'action' => 'BAD',
            'old_state' => null,
            'new_state' => null,
            'performed_by' => 'user-abc',
            'performed_at' => '2026-06-06T12:00:00.000000+00:00',
        ]);
    }

    public function test_fromArray_preserves_exact_timestamp(): void
    {
        $data = [
            'id' => self::UUID,
            'aggregate_type' => 'order',
            'aggregate_id' => '42',
            'action' => 'DELETE',
            'old_state' => ['status' => 'shipped'],
            'new_state' => null,
            'performed_by' => 'admin',
            'performed_at' => '2025-01-01T00:00:00.000000+00:00',
        ];

        $entry = AuditEntry::fromArray($data);

        $this->assertSame(
            '2025-01-01T00:00:00.000000+00:00',
            $entry->toArray()['performed_at'],
        );
    }
}
