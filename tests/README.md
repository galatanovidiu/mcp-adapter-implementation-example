# Test Suite

This directory contains the test suite for the MCP Adapter Implementation Example plugin.

## Quick Start

```bash
# Install dependencies
composer install

# Run quick tests (no WordPress required)
composer test

# Run full tests with wp-env
composer env:setup
composer test:env
```

## Test Files

- **`quick-test.php`** - Fast structure validation (no WordPress required)
- **`unit-test-simple.php`** - Alternative simple test runner
- **`bootstrap.php`** - PHPUnit bootstrap for WordPress tests
- **`TestCase.php`** - Base test case class
- **`Unit/`** - Unit tests for individual abilities
- **`Integration/`** - Integration tests for full workflows

## Test Commands

| Command | Description | Requirements |
|---------|-------------|--------------|
| `composer test` | Quick structure tests | PHP + Composer |
| `composer test:env` | Full WordPress tests | Docker + Node.js |
| `composer test:all` | Tests + linting | PHP + Composer |
| `composer dev` | Start dev environment | Docker + Node.js |

## More Information

See [TESTING.md](../TESTING.md) for complete testing guide.