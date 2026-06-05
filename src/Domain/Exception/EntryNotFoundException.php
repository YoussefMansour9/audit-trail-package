<?php

declare(strict_types=1);

namespace AuditTrail\Domain\Exception;

class EntryNotFoundException extends AuditTrailException
{
    public function __construct(string $entryId)
    {
        parent::__construct(sprintf('Audit entry not found: "%s"', $entryId));
    }
}
