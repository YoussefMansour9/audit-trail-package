<?php

declare(strict_types=1);

namespace AuditTrail\Tests\Domain;

use AuditTrail\Domain\Action;
use PHPUnit\Framework\TestCase;

final class ActionTest extends TestCase
{
    public function test_cases_have_expected_values(): void
    {
        $this->assertSame('CREATE', Action::CREATE->value);
        $this->assertSame('UPDATE', Action::UPDATE->value);
        $this->assertSame('DELETE', Action::DELETE->value);
    }

    public function test_cases_count(): void
    {
        $this->assertCount(3, Action::cases());
    }

    public function test_from_valid_string_value(): void
    {
        $action = Action::from('CREATE');
        $this->assertSame(Action::CREATE, $action);
    }

    public function test_try_from_valid_string(): void
    {
        $this->assertSame(Action::CREATE, Action::tryFrom('CREATE'));
    }

    public function test_try_from_invalid_string_returns_null(): void
    {
        $this->assertNull(Action::tryFrom('INVALID'));
    }
}
