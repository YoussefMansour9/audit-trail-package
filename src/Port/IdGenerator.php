<?php

declare(strict_types=1);

namespace AuditTrail\Port;

interface IdGenerator
{
    public function generate(): string;
}
