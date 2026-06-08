# Audit Trail Package

[![PHP](https://img.shields.io/badge/PHP-8.3+-6f4c8e?logo=php)](https://php.net)
[![CI](https://github.com/YoussefMansour9/audit-trail-package/actions/workflows/ci.yml/badge.svg)](https://github.com/YoussefMansour9/audit-trail-package/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)](https://phpstan.org)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A **production-grade** Composer package for tracking entity changes with complete audit history. Built with **Clean Architecture**, **CQRS**, **Repository Pattern**, and **Dependency Injection**.

---

## Features

- **Record** entity changes (CREATE, UPDATE, DELETE) with full before/after state
- **Query** audit history by entity, user, date range, or action type
- **Immutable entries** — once written, never modified
- **Batch recording** with all-or-nothing validation
- **Storage-agnostic** architecture (PDO implementation included)
- **PSR-3 logging** — plug any logger (Monolog, Sentry, etc.)
- **PHP 8.3+** with strict types everywhere
- **Zero impact** on your entity tables — audit data is stored separately

---

## Requirements

- PHP 8.3+
- PDO extension (for the included MySQL implementation)
- MySQL 5.7+ or MariaDB 10.2+ (for JSON column support)

---

## Installation

```bash
composer require youssefmansour9/audit-trail-package
```

---

## Quick Start

```php
use AuditTrail\AuditTrail;

// 1. Connect to your database
$pdo = new \PDO('mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4', 'root', '');
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

// 2. Create the audit_log table (see src/Infrastructure/Persistence/Schema/mysql.sql)
//    or run: mysql -u root myapp < vendor/youssefmansour9/audit-trail-package/src/Infrastructure/Persistence/Schema/mysql.sql

// 3. Create the audit trail instance
$auditTrail = AuditTrail::createWithPdo($pdo);

// 4. Start tracking
$oldState = ['status' => 'pending', 'total' => 100.00];
$newState = ['status' => 'shipped', 'total' => 100.00];

$entry = $auditTrail->recordChange(
    aggregateType: 'order',
    aggregateId: '42',
    action: 'UPDATE',
    oldState: $oldState,
    newState: $newState,
    performedBy: 'user-abc-123',
);

// 5. Query history
$history = $auditTrail->getHistory('order', '42');
```

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│                Consumer Code                     │
│  (Controller, CLI, Framework)                    │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│            AuditTrail (Facade)                   │
│  Public API — the only class you instantiate     │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│            AuditService (Application)            │
│  Orchestrates use cases, validates transitions   │
│  Depends only on interfaces (ports)              │
└──────────────────────┬──────────────────────────┘
                       │
          ┌────────────┴────────────┐
          ▼                         ▼
┌──────────────────┐     ┌──────────────────┐
│ AuditRepository  │     │ LoggerInterface  │
│   (Port/Interface)│     │  (PSR-3 Port)    │
└──────────────────┘     └──────────────────┘
          │                         │
          ▼                         ▼
┌──────────────────┐     ┌──────────────────┐
│ PDOAuditRepo     │     │ Monolog / Null   │
│  (Infrastructure)│     │  (Infrastructure) │
└──────────────────┘     └──────────────────┘
```

### Clean Architecture layers

| Layer | Folder | Responsibility |
|---|---|---|
| **Domain** | `src/Domain/` | Business value objects, enums, exceptions. Zero dependencies. |
| **Port** | `src/Port/` | Interface contracts (Repository, Logger). Inverts dependencies. |
| **Application** | `src/Application/` | Use-case orchestration, validation rules. Depends only on Ports. |
| **Infrastructure** | `src/Infrastructure/` | Concrete implementations (PDO). Swappable. |
| **Facade** | `src/AuditTrail.php` | Single entry point. DI-friendly. |

### Design patterns

| Pattern | Where | Purpose |
|---|---|---|
| Clean Architecture | Entire structure | Separation of concerns, testability, swappability |
| CQRS | `AuditRepository` | Command methods (`append`) separated from Query methods (`findBy*`) |
| Repository | `AuditRepository` + `PDOAuditRepository` | Abstracts storage behind a collection-like interface |
| Dependency Injection | Every class | Dependencies provided via constructor, never created internally |
| Value Object | `AuditEntry` | Immutable, self-validating, equality by data |
| Facade | `AuditTrail` | Simplified public API hiding internal complexity |
| Static Factory | `AuditEntry::record()`, `AuditEntry::fromArray()` | Named constructors for different creation contexts |
| Primary Constructor | `AuditEntry` | Private constructor + public named constructors |
| Adapter | `PDOAuditRepository` | Adapts PDO to the Repository interface |
| Null Object | `NullLogger` | Default no-op logger when none provided |

---

## Usage

### Recording changes

```php
// CREATE — oldState must be null, newState must be provided
$auditTrail->recordChange('order', '42', 'CREATE', null, ['status' => 'pending'], 'user-1');

// UPDATE — both oldState and newState must be provided
$auditTrail->recordChange('order', '42', 'UPDATE', $oldState, $newState, 'user-1');

// DELETE — newState must be null, oldState must be provided
$auditTrail->recordChange('order', '42', 'DELETE', $oldState, null, 'user-1');
```

### Batch recording (atomic)

```php
use AuditTrail\Application\DTO\ChangeRequest;

$auditTrail->recordBatch([
    new ChangeRequest('order', '1', 'CREATE', null,             ['status' => 'pending'],  'user-1'),
    new ChangeRequest('order', '1', 'UPDATE', ['status' => 'pending'], ['status' => 'shipped'], 'user-1'),
    new ChangeRequest('order', '1', 'DELETE', ['status' => 'shipped'], null,                     'user-1'),
]);
```

All entries are validated before any are persisted. If the second entry fails validation, **no** entries are written.

### Querying history

```php
// Full revision history of an entity (oldest first)
$history = $auditTrail->getHistory('order', '42');
foreach ($history as $entry) {
    echo $entry->performedAt()->format('Y-m-d H:i:s') . ' — ' . $entry->action()->value;
}

// Specific entry by ID
$entry = $auditTrail->getEntry('uuid-here');

// All changes by a user (newest first)
$entries = $auditTrail->getEntriesByUser('user-abc-123');

// All changes in a date range
$entries = $auditTrail->getEntriesByDateRange(
    new \DateTimeImmutable('-7 days'),
    new \DateTimeImmutable('now'),
);

// Count changes for an entity
$count = $auditTrail->countByAggregate('order', '42');
```

### With Monolog

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use AuditTrail\AuditTrail;

$logger = new Logger('audit');
$logger->pushHandler(new StreamHandler('/var/log/audit.log', Logger::INFO));

// Pass it to the factory
$auditTrail = AuditTrail::createWithPdo($pdo, $logger);

// Or wire manually with any PSR-3 logger
use AuditTrail\Infrastructure\RandomBytesIdGenerator;

$service = new AuditService(
    new PDOAuditRepository($pdo),
    $logger,
    new RandomBytesIdGenerator(),
);
$auditTrail = new AuditTrail($service);
```

---

## Database Schema

```sql
CREATE TABLE audit_log (
    id              VARCHAR(36)    NOT NULL PRIMARY KEY,
    aggregate_type  VARCHAR(255)   NOT NULL,
    aggregate_id    VARCHAR(255)   NOT NULL,
    action          VARCHAR(10)    NOT NULL,
    old_state       JSON           DEFAULT NULL,
    new_state       JSON           DEFAULT NULL,
    performed_by    VARCHAR(255)   NOT NULL,
    performed_at    DATETIME(6)    NOT NULL,
    metadata        JSON           DEFAULT NULL,

    INDEX idx_audit_aggregate   (aggregate_type, aggregate_id),
    INDEX idx_audit_performed_by (performed_by),
    INDEX idx_audit_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The schema file is located at `src/Infrastructure/Persistence/Schema/mysql.sql`.

### Column guide

| Column | Type | Purpose |
|---|---|---|
| `id` | UUID v4 | Unique identifier for the audit entry |
| `aggregate_type` | string | Entity type (e.g., "order", "user", "product") |
| `aggregate_id` | string | Entity identifier (e.g., "42", "uuid-value") |
| `action` | enum string | One of: CREATE, UPDATE, DELETE |
| `old_state` | JSON or null | The entity state before this change |
| `new_state` | JSON or null | The entity state after this change |
| `performed_by` | string | Who performed the action |
| `performed_at` | datetime(6) | Microsecond-precision timestamp |
| `metadata` | JSON or null | Extra context (IP, request ID, tags) |

---

## Testing

```bash
# Run all tests
vendor/bin/phpunit

# Run static analysis
vendor/bin/phpstan analyse src --level 9

# Code coverage (requires xdebug)
vendor/bin/phpunit --coverage-html coverage/
```

Tests use **SQLite in-memory** — no database server required. The test suite runs on CI with zero external dependencies.

### Test structure

| Test suite | Tests | What it covers |
|---|---|---|
| `Domain\ActionTest` | 6 | Enum values, cases, from/tryFrom |
| `Domain\AuditEntryTest` | 20 | Creation, serialization, reconstruction, validation |
| `Domain\Exception\ExceptionTest` | 5 | Exception hierarchy and messages |
| `Application\AuditServiceTest` | 17 | Use cases, validation rules, batch atomicity |
| `Infrastructure\PDOAuditRepositoryTest` | 17 | Full CRUD with SQLite, ordering, pagination, batch rollback |
| `AuditTrailTest` | 8 | Public facade delegation, createWithPdo factory |

---

## PHPStan

This project enforces **level 9** (the maximum — property type hints, return type hints, generic array annotations, mixed type warnings, and strict comparison rules):

```bash
vendor/bin/phpstan analyse src --level 9
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

MIT — see [LICENSE](LICENSE).

Copyright (c) 2026 Youssef Mansour
