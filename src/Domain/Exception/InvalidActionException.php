<?php

declare(strict_types=1);

namespace AuditTrail\Domain\Exception;

class InvalidActionException extends AuditTrailException
{
    public function __construct(string $action)
    {
        parent::__construct(sprintf('Invalid audit action: "%s". Allowed: CREATE, UPDATE, DELETE', $action));
    }
}
