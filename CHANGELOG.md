# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 — 2026-06-06

Initial release.

### Added

- Record audit entries (CREATE, UPDATE, DELETE)
- Batch recording with atomic validation (all-or-nothing)
- Query history by aggregate (entity type + ID)
- Query entries by user
- Query entries by date range
- Count entries by aggregate
- Retrieve specific entry by ID
- Immutable `AuditEntry` value object with `toArray()` / `fromArray()` serialization
- State transition validation (CREATE → null oldState, etc.)
- PDO repository implementation (MySQL compatible)
- SQLite in-memory test suite
- PHPStan level 6 static analysis
- PSR-3 logging support
- Clean Architecture with CQRS, Repository, DI, and Adapter patterns
