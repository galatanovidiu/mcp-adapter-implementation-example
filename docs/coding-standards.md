# Coding Standards

This document describes the coding standards setup for the MCP Adapter Implementation Example plugin.

## Overview

The plugin follows WordPress Coding Standards with additional modern PHP standards for clean, maintainable code. The setup includes:

- **PHPCS (PHP_CodeSniffer)** for code style checking and automatic fixing
- **PHPStan** for static analysis and type checking
- **WordPress VIP Go standards** for performance and security best practices
- **Slevomat Coding Standard** for modern PHP practices

## Tools and Configuration

### PHP_CodeSniffer (PHPCS)

**Configuration**: `phpcs.xml.dist`

**Standards Used**:
- WordPress-VIP-Go (performance and security)
- WordPress-Extra (WordPress-specific rules)
- PHPCompatibilityWP (PHP version compatibility)
- SlevomatCodingStandard (modern PHP practices)

**Custom Rules**:
- Allows PSR-4 class/file naming instead of WordPress naming scheme
- Permits modern PHP syntax (short ternary operators, etc.)
- Allows custom taxonomy capabilities (`manage_terms`, `assign_terms`, etc.)
- Excludes debug functions in the main plugin file (for demonstration purposes)
- Excludes slow query warnings in example abilities

### PHPStan

**Configuration**: `phpstan.neon.dist`

**Level**: 8 (strictest)
**PHP Version**: 8.1+
**Features**:
- Type inference and checking
- WordPress-specific analysis via `szepeviktor/phpstan-wordpress`
- Deprecation rule checking
- Integration with WordPress and Abilities API

### Composer Scripts

Available commands for code quality:

```bash
# Check code style
composer lint:php

# Fix code style automatically
composer lint:php:fix

# Run static analysis
composer lint:php:stan

# Alternative command for fixing
composer format
```

## Usage

### Daily Development

1. **Before committing**, run the linter:
   ```bash
   composer lint:php
   ```

2. **Fix issues automatically** when possible:
   ```bash
   composer lint:php:fix
   ```

3. **Run static analysis** for type safety:
   ```bash
   composer lint:php:stan
   ```

### IDE Integration

Most modern IDEs can integrate with PHPCS and PHPStan:

**VS Code**:
- Install "PHP Sniffer & Beautifier" extension
- Install "PHP Intelephense" extension
- Configure workspace settings to use the local `phpcs.xml.dist`

**PhpStorm**:
- Enable PHP_CodeSniffer in Settings → PHP → Quality Tools
- Set coding standard to "Custom" and point to `phpcs.xml.dist`
- Enable PHPStan inspection

## Current Status

After setup and fixes:
- ✅ **No coding standard errors**
- ⚠️ **Only warnings remain** (mostly about dynamic capability checking)
- ✅ **PHPStan passes** with level 8 strictness
- ✅ **811 violations automatically fixed**

### Remaining Warnings

The remaining warnings are intentional and expected:

1. **Undetermined Capabilities**: Dynamic capability checking from taxonomy objects (`$tax->cap->edit_terms`)
2. **Slow Query Warnings**: Intentionally allowed in example abilities to demonstrate functionality
3. **VIP Performance Warnings**: About exclusionary parameters in `get_terms()` calls

These warnings don't indicate actual problems but rather highlight areas where WordPress VIP Go has specific performance recommendations.

## File Structure

```
├── phpcs.xml.dist          # PHPCS configuration
├── phpstan.neon.dist       # PHPStan configuration
├── composer.json           # Dependencies and scripts
└── tests/
    └── _output/
        ├── .gitkeep        # Keep directory in git
        └── phpcs-cache.json # PHPCS cache (auto-generated)
```

## Dependencies

### Production Dependencies
- `automattic/jetpack-autoloader`: Autoloading system
- `wordpress/mcp-adapter`: Core MCP functionality
- `wordpress/abilities-api`: WordPress abilities system

### Development Dependencies
- `automattic/vipwpcs`: WordPress VIP Go standards
- `wp-coding-standards/wpcs`: WordPress coding standards
- `phpstan/phpstan`: Static analysis tool
- `slevomat/coding-standard`: Modern PHP standards
- `phpcompatibility/phpcompatibility-wp`: PHP version compatibility

## Best Practices

1. **Run linting before commits** to catch issues early
2. **Use automatic fixing** to resolve style issues quickly
3. **Address PHPStan errors** for better type safety
4. **Keep configuration updated** as standards evolve
5. **Document exceptions** when disabling specific rules

## Troubleshooting

### Common Issues

**"Standards not found" error**:
```bash
composer update
```

**Cache issues**:
```bash
rm tests/_output/phpcs-cache.json
composer lint:php
```

**Memory issues with PHPStan**:
```bash
composer lint:php:stan -- --memory-limit=2G
```

### Performance

- PHPCS cache is enabled for faster subsequent runs
- Parallel processing is enabled for PHPCS (20 processes)
- PHPStan results are cached automatically

This setup ensures consistent, high-quality code that follows WordPress and modern PHP best practices.
