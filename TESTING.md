# Testing Guide

This guide explains how to run tests for the MCP Adapter Implementation Example plugin.

## 🚀 Quick Start

### Option 1: Quick Tests (No Setup Required)
```bash
# Install dependencies and run quick tests
composer install
composer test

# This runs structure validation without needing WordPress or database
```

### Option 2: Full Tests with wp-env (Recommended)
```bash
# Prerequisites: Docker Desktop + Node.js
composer env:setup              # Install npm deps and start wp-env
composer test:env               # Run full test suite in wp-env

# Or step by step:
npm install                     # Install wp-env
npm run env:start               # Start WordPress environment
composer test:env               # Run tests
npm run env:stop                # Stop environment when done
```

## 📋 Available Test Commands

### Quick Tests (No WordPress Required)
```bash
composer test                   # Default: Quick structure validation
composer test:quick             # Same as above
composer test:simple            # Alternative simple test
```

### Full WordPress Tests (Requires wp-env)
```bash
composer test:env               # All tests in wp-env
composer test:env:unit          # Unit tests only
composer test:env:integration   # Integration tests only
composer test:env:coverage      # Generate coverage report
composer test:full              # Start wp-env, run tests, stop wp-env
```

### Code Quality
```bash
composer lint:php               # Check code style
composer lint:php:fix           # Auto-fix style issues
composer lint:php:stan          # Static analysis
composer test:all               # Quick tests + linting + static analysis
```

### Development Environment
```bash
composer dev                    # Start development environment
composer env:start              # Start wp-env
composer env:stop               # Stop wp-env
composer env:clean              # Clean wp-env data
```

## 🧪 Test Types

### 1. Quick Tests (Default)
- **Purpose**: Validate plugin structure, dependencies, and class interfaces
- **Command**: `composer test` or `composer test:quick`
- **Requirements**: None (no database or WordPress needed)
- **Speed**: ⚡ Fast (< 10 seconds)
- **What's tested**: Autoloading, class structure, dependencies, file structure

### 2. Unit Tests
- **Purpose**: Test individual abilities in isolation
- **Command**: `composer test:env:unit`
- **Requirements**: wp-env (Docker + Node.js)
- **Speed**: 🐌 Medium (30-60 seconds)
- **What's tested**: Individual ability classes, permission checks, validation

### 3. Integration Tests
- **Purpose**: Test full MCP Adapter + Abilities API integration
- **Command**: `composer test:env:integration`
- **Requirements**: wp-env (Docker + Node.js)
- **Speed**: 🐌 Slow (60-120 seconds)
- **What's tested**: End-to-end workflows, API responses, error handling

## 🛠️ Setup Instructions

### For Quick Tests (Recommended for CI/Development)
```bash
# Only requirement: PHP + Composer
composer install
composer test
```

### For Full Tests with wp-env
```bash
# Prerequisites: Docker Desktop + Node.js 18+

# One-time setup
composer env:setup

# Run tests anytime
composer test:env

# Development workflow
composer dev                    # Start development environment
# Visit: http://localhost:8890 (admin/password)
```

### Manual wp-env Setup
```bash
# Step by step
npm install                     # Install @wordpress/env
npm run env:start               # Start WordPress containers
composer test:env               # Run tests
npm run env:stop                # Stop containers
```

## Creating Tests

### Test File Structure
```
tests/
├── Unit/                      # Fast unit tests
│   ├── Posts/CreatePostTest.php
│   ├── Posts/ListPostsTest.php
│   ├── Blocks/ListBlockTypesTest.php
│   └── Taxonomies/GetTermsTest.php
├── Integration/               # WordPress integration tests
│   ├── McpAdapterIntegrationTest.php
│   ├── McpServerFunctionalityTest.php
│   ├── EndToEndWorkflowTest.php
│   └── SchemaValidationTest.php
└── Utilities/                 # Test helpers
    └── TestCase.php
```

### Writing Unit Tests

Create a new test file in `tests/Unit/[Category]/[AbilityName]Test.php`:

```php
<?php

namespace WpMcpExample\Tests\Unit\Posts;

use WpMcpExample\Tests\Utilities\TestCase;
use WpMcpExample\Abilities\Posts\CreatePost;

class CreatePostTest extends TestCase {
    
    public function test_ability_registration(): void {
        $this->assertAbilityRegistered('wpmcp-example/create-post');
    }
    
    public function test_permission_check_with_valid_user(): void {
        wp_set_current_user(1); // Admin user
        $ability = new CreatePost();
        $result = $ability->execute(['post_type' => 'post', 'title' => 'Test']);
        $this->assertNotWPError($result);
    }
    
    public function test_validation_with_invalid_input(): void {
        $ability = new CreatePost();
        $result = $ability->execute(['invalid' => 'data']);
        $this->assertWPError($result);
    }
}
```

### Writing Integration Tests

Create integration tests in `tests/Integration/[TestName]Test.php`:

```php
<?php

namespace WpMcpExample\Tests\Integration;

use WpMcpExample\Tests\Utilities\TestCase;

class McpServerFunctionalityTest extends TestCase {
    
    public function test_mcp_tool_execution(): void {
        $response = $this->make_mcp_request('tools/call', [
            'name' => 'wpmcp-example--create-post',
            'arguments' => ['post_type' => 'post', 'title' => 'Test Post']
        ]);
        
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertArrayHasKey('result', $data);
    }
    
    public function test_error_handling(): void {
        $response = $this->make_mcp_request('tools/call', [
            'name' => 'wpmcp-example--create-post',
            'arguments' => ['post_type' => 'invalid_type']
        ]);
        
        $this->assertEquals(500, $response->get_status());
    }
}
```

### Test Naming Conventions
- **Test Classes**: `[ComponentName]Test` (e.g., `CreatePostTest`)
- **Test Methods**: `test_[scenario_description]` (e.g., `test_permission_check_with_valid_user`)
- **Test Files**: Match class names with `.php` extension

### Test Utilities

The `TestCase` base class provides helpful methods:

```php
// Create test data with automatic cleanup
$post_id = $this->create_test_post(['post_title' => 'Test Post']);
$term_id = $this->create_test_term('Test Category', 'category');

// Make MCP requests
$response = $this->make_mcp_request('tools/call', $params);

// Assertions
$this->assertAbilityRegistered('ability/name');
$this->assertToolRegistered('tool-name');
$this->assertNotWPError($result);
```

## Coverage Reports

Generate test coverage reports:
```bash
composer test:coverage
open tests/_output/coverage/index.html
```

## 🚨 Troubleshooting

### Quick Test Issues
```bash
# Dependencies not installed
composer install

# Permission issues
chmod +x run-tests.sh

# PHP version issues
php --version  # Requires PHP 7.4+
```

### wp-env Issues

#### Docker not running
```bash
# Check Docker status
docker ps

# Start Docker Desktop (macOS)
open -a Docker

# Start Docker (Linux)
sudo systemctl start docker
```

#### Port conflicts
```bash
# Check what's using ports 8890/8891
lsof -i :8890
lsof -i :8891

# Kill processes if needed
sudo lsof -ti:8890 | xargs kill -9
```

#### Node.js version
```bash
# Check Node version (requires 18+)
node --version

# Update Node.js if needed
nvm install 18
nvm use 18
```

### Debug Commands
```bash
# View WordPress logs
npm run wp-env logs

# Access WordPress CLI
npm run wp-env run tests-cli wp --info

# Reset environment
npm run env:clean
npm run env:start

# Check container status
docker ps
```

## 🎯 Testing Workflow Examples

### For Contributors
```bash
# 1. Quick validation during development
composer test

# 2. Full validation before PR
composer test:all

# 3. Test specific functionality
composer test:env:unit
```

### For CI/CD
```bash
# Lightweight CI (GitHub Actions, etc.)
composer install
composer test:all

# Full CI with WordPress
composer env:setup
composer test:env
composer env:stop
```

### For Local Development
```bash
# Start development environment
composer dev

# Make changes...

# Quick test
composer test

# Full test when ready
composer test:env
```