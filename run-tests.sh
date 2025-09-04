#!/bin/bash

# Test runner script for MCP Adapter Implementation Example
# This script helps run tests in different configurations

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if composer is available
if ! command -v composer &> /dev/null; then
    print_error "Composer is not installed or not in PATH"
    exit 1
fi

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    print_status "Installing dependencies..."
    composer install
fi

# Function to run tests
run_tests() {
    local test_type="$1"
    local additional_args="$2"
    
    print_status "Running $test_type tests..."
    
    # Check if WordPress test environment is available
    if ! check_wp_test_environment; then
        print_error "WordPress test environment not available"
        print_status "Running structure validation instead..."
        php tests/unit-test-simple.php
        return $?
    fi
    
    case "$test_type" in
        "unit")
            composer test:unit $additional_args
            ;;
        "integration")
            composer test:integration $additional_args
            ;;
        "coverage")
            print_status "Generating coverage report..."
            composer test:coverage $additional_args
            print_success "Coverage report generated in tests/_output/coverage/"
            ;;
        "all"|*)
            composer test $additional_args
            ;;
    esac
}

# Function to check WordPress test environment
check_wp_test_environment() {
    # Check if MySQL is running
    if ! mysql_check; then
        print_warning "MySQL/MariaDB not running or not accessible"
        return 1
    fi
    
    # Check if WordPress test suite is available
    if [ ! -f "/tmp/wordpress-tests-lib/includes/functions.php" ]; then
        print_warning "WordPress test suite not installed"
        print_status "Run './run-tests.sh setup' to install it"
        return 1
    fi
    
    return 0
}

# Function to check MySQL connectivity
mysql_check() {
    if command -v mysql &> /dev/null; then
        # Try to connect to MySQL
        if mysql -u root --password="" -e "SELECT 1;" &> /dev/null; then
            return 0
        elif mysql -u root -e "SELECT 1;" &> /dev/null; then
            return 0
        fi
    fi
    return 1
}

# Function to check test environment
check_environment() {
    print_status "Checking test environment..."
    
    # Check WordPress test environment variables
    if [ -z "$WP_TESTS_DIR" ] && [ -z "$WP_DEVELOP_DIR" ] && [ -z "$WP_PHPUNIT__DIR" ]; then
        print_warning "WordPress test environment not configured"
        print_warning "Set one of: WP_TESTS_DIR, WP_DEVELOP_DIR, or WP_PHPUNIT__DIR"
        print_warning "Tests will use fallback: /tmp/wordpress-tests-lib"
    else
        print_success "WordPress test environment configured"
    fi
    
    # Check if required classes exist
    php -r "
    require_once 'vendor/autoload.php';
    if (!class_exists('WP_UnitTestCase')) {
        echo 'ERROR: WP_UnitTestCase not available\n';
        exit(1);
    }
    if (!interface_exists('OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility')) {
        echo 'ERROR: Plugin classes not autoloaded\n';
        exit(1);
    }
    echo 'SUCCESS: Required classes available\n';
    " || exit 1
}

# Function to setup WordPress tests
setup_wordpress_tests() {
    print_status "Setting up WordPress test environment..."
    
    # Default test database configuration
    DB_NAME=${DB_NAME:-wordpress_test}
    DB_USER=${DB_USER:-root}
    DB_PASS=${DB_PASS:-}
    DB_HOST=${DB_HOST:-localhost}
    WP_VERSION=${WP_VERSION:-latest}
    
    # Download and setup WordPress tests if needed
    if [ ! -d "/tmp/wordpress-tests-lib" ]; then
        print_status "Downloading WordPress test suite..."
        
        # Create temporary directory
        mkdir -p /tmp/wordpress-tests-lib
        
        # Note: In a real environment, you'd download the WordPress test suite
        # For now, we'll just create the directory structure
        print_warning "WordPress test suite setup incomplete"
        print_warning "Please set up WordPress test environment manually"
    fi
}

# Function to lint code
lint_code() {
    print_status "Running code linting..."
    
    if composer lint:php; then
        print_success "Code linting passed"
    else
        print_error "Code linting failed"
        return 1
    fi
}

# Function to run static analysis
static_analysis() {
    print_status "Running static analysis..."
    
    if composer lint:php:stan; then
        print_success "Static analysis passed"
    else
        print_error "Static analysis failed"
        return 1
    fi
}

# Main script logic
main() {
    local command="$1"
    local additional_args="${@:2}"
    
    case "$command" in
        "setup")
            check_environment
            setup_wordpress_tests
            ;;
        "unit")
            run_tests "unit" "$additional_args"
            ;;
        "integration")
            run_tests "integration" "$additional_args"
            ;;
        "coverage")
            run_tests "coverage" "$additional_args"
            ;;
        "lint")
            lint_code
            ;;
        "stan")
            static_analysis
            ;;
        "ci")
            print_status "Running full CI pipeline..."
            check_environment
            lint_code
            static_analysis
            run_tests "all" "$additional_args"
            print_success "CI pipeline completed successfully"
            ;;
        "quick")
            print_status "Running quick validation tests (no database required)..."
            php tests/unit-test-simple.php
            ;;
        "wp-env")
            print_status "Setting up wp-env environment for testing..."
            if ! command -v npm &> /dev/null; then
                print_error "npm is not installed. Please install Node.js and npm first."
                exit 1
            fi
            
            print_status "Installing wp-env dependencies..."
            npm install
            
            print_status "Starting wp-env..."
            npm run env:start
            
            print_success "wp-env started successfully!"
            print_status "WordPress development site: http://localhost:8888"
            print_status "WordPress admin: http://localhost:8888/wp-admin (admin/password)"
            print_status "WordPress test site: http://localhost:8889"
            
            print_status "Running tests in wp-env..."
            npm run test:php
            ;;
        "wp-env:stop")
            print_status "Stopping wp-env environment..."
            if [ -f "package.json" ]; then
                npm run env:stop
                print_success "wp-env stopped successfully!"
            else
                print_warning "package.json not found. Install wp-env first with './run-tests.sh wp-env'"
            fi
            ;;
        "help"|"-h"|"--help")
            echo "Usage: $0 [command] [options]"
            echo ""
            echo "Commands:"
            echo "  quick       Run quick validation tests (no database)"
            echo "  wp-env      Setup and run tests with wp-env (recommended)"
            echo "  wp-env:stop Stop wp-env environment"
            echo "  setup       Setup test environment"
            echo "  unit        Run unit tests"
            echo "  integration Run integration tests"
            echo "  coverage    Generate coverage report"
            echo "  lint        Run code linting"
            echo "  stan        Run static analysis"
            echo "  ci          Run complete CI pipeline"
            echo "  help        Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0 quick      # Fast validation without database"
            echo "  $0 wp-env     # Full tests with wp-env (recommended)"
            echo "  $0 unit       # Full unit tests (needs database)"
            echo "  $0 integration"
            echo "  $0 coverage"
            echo "  $0 ci"
            ;;
        "")
            print_status "Running all tests..."
            run_tests "all" "$additional_args"
            ;;
        *)
            print_error "Unknown command: $command"
            echo "Use '$0 help' for usage information"
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"
