<?php

declare(strict_types=1);

namespace AuditTrail\Infrastructure;

use AuditTrail\Port\IdGenerator;

final class RandomBytesIdGenerator implements IdGenerator
{
    public function generate(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6)),
        );
    }
}
