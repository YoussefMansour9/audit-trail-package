# Contributing

Contributions are welcome! Here's how to get started.

## Setup

```bash
git clone git@github.com:YoussefMansour9/audit-trail-package.git
cd audit-trail-package
composer install
```

## Running Tests

```bash
vendor/bin/phpunit
```

All tests use SQLite in-memory — no database server required.

## Static Analysis

```bash
vendor/bin/phpstan analyse src --level 6
```

## Code Style

- Follow **PSR-12** coding style
- Use **strict types** (`declare(strict_types=1)`) in every file
- Use **constructor promotion** for DI (`private readonly Type $dependency`)
- Name methods with **intention-revealing** names (e.g., `recordChange`, not `save`)
- Write **PHPDoc** with `@param` and `@return` for array generics
- Keep **methods small** — if a method exceeds 15-20 lines, extract helpers

## Pull Request Process

1. Fork the repository
2. Create a feature branch (`git checkout -b feat/my-feature`)
3. Write tests for your changes
4. Ensure all tests pass and PHPStan reports no errors
5. Submit a PR with a clear description of the change

## Commit Messages

Use conventional commits:

```
feat: add findByDateRange query
fix: handle null metadata in fromArray
docs: update README with batch examples
test: add tests for countByAggregate
```
