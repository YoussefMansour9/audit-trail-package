<?php

declare(strict_types=1);

namespace AuditTrail\Domain;

enum Action: string
{
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
}
