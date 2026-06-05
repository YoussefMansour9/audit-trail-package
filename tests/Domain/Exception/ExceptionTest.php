<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Domain\Exception;

use AuditTrail\Domain\Exception\AuditTrailException;
use AuditTrail\Domain\Exception\EntryNotFoundException;
use AuditTrail\Domain\Exception\InvalidActionException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    public function test_audit_trail_exception_is_runtime_exception(): void
    {
        $exception = new AuditTrailException('test');
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function test_invalid_action_exception_message(): void
    {
        $exception = new InvalidActionException('FOO');
        $this->assertStringContainsString('FOO', $exception->getMessage());
        $this->assertStringContainsString('CREATE, UPDATE, DELETE', $exception->getMessage());
    }

    public function test_invalid_action_exception_is_audit_trail_exception(): void
    {
        $this->assertInstanceOf(AuditTrailException::class, new InvalidActionException('FOO'));
    }

    public function test_entry_not_found_exception_message(): void
    {
        $exception = new EntryNotFoundException('entry-123');
        $this->assertStringContainsString('entry-123', $exception->getMessage());
    }

    public function test_entry_not_found_exception_is_audit_trail_exception(): void
    {
        $this->assertInstanceOf(AuditTrailException::class, new EntryNotFoundException('x'));
    }
}
