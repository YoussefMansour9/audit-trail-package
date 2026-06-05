<?php

declare(strict_types=1);

namespace AuditTrail\Application\Exception;

use AuditTrail\Domain\Exception\AuditTrailException;

class InvalidStateTransitionException extends AuditTrailException
{
    public function __construct(string $action, string $reason)
    {
        parent::__construct(sprintf(
            'Invalid state transition for action "%s": %s',
            $action,
            $reason,
        ));
    }
}
