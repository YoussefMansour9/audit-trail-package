<?php

declare(strict_types=1);

namespace AuditTrail\Application\DTO;

final class ChangeRequest
{
    /**
     * @param array<string, mixed>|null $oldState
     * @param array<string, mixed>|null $newState
     * @param array<string, mixed>|null $metadata
     */
    public function __construct(
        public readonly string $aggregateType,
        public readonly string $aggregateId,
        public readonly string $action,
        public readonly ?array $oldState = null,
        public readonly ?array $newState = null,
        public readonly string $performedBy = '',
        public readonly ?array $metadata = null,
    ) {
    }
}
