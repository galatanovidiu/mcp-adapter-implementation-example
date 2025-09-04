<?php
/**
 * Quick test runner - no WordPress database required.
 * This script validates plugin structure and basic functionality.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

// Ensure we can run from any directory.
$plugin_root = dirname( __DIR__ );
chdir( $plugin_root );

// Load Composer dependencies.
if ( ! file_exists( 'vendor/autoload.php' ) ) {
	echo "❌ ERROR: Composer dependencies not installed.\n";
	echo "💡 Run: composer install\n\n";
	exit( 1 );
}

require_once 'vendor/autoload.php';

// Test counter.
$tests_run    = 0;
$tests_passed = 0;
$test_errors  = array();

/**
 * Simple assertion function.
 */
function assert_test( bool $condition, string $message ): void {
	global $tests_run, $tests_passed, $test_errors;
	++$tests_run;

	if ( $condition ) {
		++$tests_passed;
		echo "✅ PASS: {$message}\n";
	} else {
		$test_errors[] = $message;
		echo "❌ FAIL: {$message}\n";
	}
}

/**
 * Test Composer setup and dependencies.
 */
function test_composer_setup(): void {
	echo "\n📦 Testing Composer Setup\n";
	echo str_repeat( '-', 50 ) . "\n";

	// Test composer.json exists and is valid.
	assert_test(
		file_exists( 'composer.json' ),
		'composer.json file exists'
	);

	if ( file_exists( 'composer.json' ) ) {
		$composer_data = json_decode( file_get_contents( 'composer.json' ), true );
		assert_test(
			json_last_error() === JSON_ERROR_NONE,
			'composer.json is valid JSON'
		);

		assert_test(
			isset( $composer_data['autoload']['psr-4'] ),
			'PSR-4 autoloading is configured'
		);
	}

	// Test vendor directory.
	assert_test(
		is_dir( 'vendor' ),
		'vendor directory exists'
	);

	assert_test(
		file_exists( 'vendor/autoload.php' ),
		'Composer autoloader exists'
	);
}

/**
 * Test plugin class structure.
 */
function test_plugin_classes(): void {
	echo "\n🏗️  Testing Plugin Classes\n";
	echo str_repeat( '-', 50 ) . "\n";

	// Test core interfaces.
	assert_test(
		interface_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
		'RegistersAbility interface is available'
	);

	// Test bootstrap class.
	assert_test(
		class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities' ),
		'BootstrapAbilities class is available'
	);

	// Test ability classes.
	$ability_classes = array(
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost'       => 'CreatePost ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts'        => 'ListPosts ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\GetPost'          => 'GetPost ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\UpdatePost'       => 'UpdatePost ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\DeletePost'       => 'DeletePost ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes'  => 'ListBlockTypes ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms'    => 'GetTerms ability',
		'OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm'  => 'CreateTerm ability',
	);

	foreach ( $ability_classes as $class_name => $description ) {
		assert_test(
			class_exists( $class_name ),
			$description . ' class is available'
		);
	}
}

/**
 * Test dependency classes.
 */
function test_dependency_classes(): void {
	echo "\n📚 Testing Dependency Classes\n";
	echo str_repeat( '-', 50 ) . "\n";

	// Test Abilities API.
	assert_test(
		class_exists( 'WP_Ability' ),
		'WP_Ability class is available'
	);

	assert_test(
		class_exists( 'WP_Abilities_Registry' ),
		'WP_Abilities_Registry class is available'
	);

	// Test MCP Adapter core.
	assert_test(
		class_exists( 'WP\MCP\Core\McpAdapter' ),
		'McpAdapter class is available'
	);

	assert_test(
		class_exists( 'WP\MCP\Core\McpServer' ),
		'McpServer class is available'
	);

	// Test transport layer.
	assert_test(
		class_exists( 'WP\MCP\Transport\Http\RestTransport' ),
		'RestTransport class is available'
	);

	assert_test(
		class_exists( 'WP\MCP\Transport\Http\StreamableTransport' ),
		'StreamableTransport class is available'
	);

	// Test error handling.
	assert_test(
		class_exists( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler' ),
		'ErrorLogMcpErrorHandler class is available'
	);

	assert_test(
		class_exists( 'WP\MCP\Infrastructure\ErrorHandling\McpErrorFactory' ),
		'McpErrorFactory class is available'
	);
}

/**
 * Test class interfaces and method signatures.
 */
function test_class_interfaces(): void {
	echo "\n🔌 Testing Class Interfaces\n";
	echo str_repeat( '-', 50 ) . "\n";

	$ability_classes = array(
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts',
		'OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes',
		'OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms',
	);

	foreach ( $ability_classes as $class_name ) {
		if ( ! class_exists( $class_name ) ) {
			continue;
		}

		$reflection       = new ReflectionClass( $class_name );
		$class_short_name = $reflection->getShortName();

		// Test interface implementation.
		assert_test(
			$reflection->implementsInterface( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
			"{$class_short_name} implements RegistersAbility interface"
		);

		// Test required methods.
		$required_methods = array( 'register', 'check_permission', 'execute' );
		foreach ( $required_methods as $method_name ) {
			assert_test(
				$reflection->hasMethod( $method_name ),
				"{$class_short_name} has {$method_name} method"
			);

			if ( ! $reflection->hasMethod( $method_name ) ) {
				continue;
			}

			$method = $reflection->getMethod( $method_name );
			assert_test(
				$method->isPublic() && $method->isStatic(),
				"{$class_short_name}::{$method_name} is public static"
			);
		}
	}
}

/**
 * Test file structure.
 */
function test_file_structure(): void {
	echo "\n📁 Testing File Structure\n";
	echo str_repeat( '-', 50 ) . "\n";

	$required_files = array(
		'mcp-adapter-implementation-example.php'    => 'Main plugin file',
		'composer.json'                             => 'Composer configuration',
		'phpunit.xml.dist'                          => 'PHPUnit configuration',
		'run-tests.sh'                              => 'Test runner script',
		'tests/bootstrap.php'                       => 'PHPUnit bootstrap',
		'tests/TestCase.php'                        => 'Base test case',
		'includes/Abilities/BootstrapAbilities.php' => 'Bootstrap abilities',
		'includes/Abilities/RegistersAbility.php'   => 'Ability interface',
	);

	foreach ( $required_files as $file => $description ) {
		assert_test(
			file_exists( $file ),
			$description . " ({$file}) exists"
		);
	}

	$required_directories = array(
		'tests/Unit'                    => 'Unit tests directory',
		'tests/Integration'             => 'Integration tests directory',
		'tests/_output'                 => 'Test output directory',
		'includes/Abilities/Posts'      => 'Posts abilities directory',
		'includes/Abilities/Blocks'     => 'Blocks abilities directory',
		'includes/Abilities/Taxonomies' => 'Taxonomies abilities directory',
	);

	foreach ( $required_directories as $dir => $description ) {
		assert_test(
			is_dir( $dir ),
			$description . " ({$dir}) exists"
		);
	}

	// Test script permissions.
	if ( ! file_exists( 'run-tests.sh' ) ) {
		return;
	}

	assert_test(
		is_executable( 'run-tests.sh' ),
		'run-tests.sh is executable'
	);
}

/**
 * Test MCP interfaces.
 */
function test_mcp_interfaces(): void {
	echo "\n🌐 Testing MCP Interfaces\n";
	echo str_repeat( '-', 50 ) . "\n";

	// Test core interfaces.
	assert_test(
		interface_exists( 'WP\MCP\Transport\Contracts\McpTransportInterface' ),
		'McpTransportInterface is available'
	);

	assert_test(
		interface_exists( 'WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface' ),
		'McpErrorHandlerInterface is available'
	);

	assert_test(
		interface_exists( 'WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface' ),
		'McpObservabilityHandlerInterface is available'
	);

	// Test interface implementations.
	if ( ! class_exists( 'WP\MCP\Transport\Http\RestTransport' ) ) {
		return;
	}

	$rest_transport_reflection = new ReflectionClass( 'WP\MCP\Transport\Http\RestTransport' );
	assert_test(
		$rest_transport_reflection->implementsInterface( 'WP\MCP\Transport\Contracts\McpTransportInterface' ),
		'RestTransport implements McpTransportInterface'
	);
}

/**
 * Test PHPUnit configuration.
 */
function test_phpunit_config(): void {
	echo "\n🧪 Testing PHPUnit Configuration\n";
	echo str_repeat( '-', 50 ) . "\n";

	if ( file_exists( 'phpunit.xml.dist' ) ) {
		$phpunit_content = file_get_contents( 'phpunit.xml.dist' );
		assert_test(
			strpos( $phpunit_content, 'tests/bootstrap.php' ) !== false,
			'PHPUnit bootstrap is configured'
		);

		assert_test(
			strpos( $phpunit_content, 'testsuite name="unit"' ) !== false,
			'Unit test suite is configured'
		);

		assert_test(
			strpos( $phpunit_content, 'testsuite name="integration"' ) !== false,
			'Integration test suite is configured'
		);

		assert_test(
			strpos( $phpunit_content, '<coverage' ) !== false,
			'Code coverage is configured'
		);
	}

	// Check if PHPUnit is available.
	assert_test(
		file_exists( 'vendor/bin/phpunit' ),
		'PHPUnit executable is available'
	);
}

// Main execution.
echo "🚀 MCP Adapter Implementation Example - Quick Test Suite\n";
echo str_repeat( '=', 70 ) . "\n";
echo "This test validates the plugin structure without requiring WordPress.\n";
echo "For full functionality tests, use: composer test:full\n\n";

// Run all test suites.
test_composer_setup();
test_plugin_classes();
test_dependency_classes();
test_class_interfaces();
test_mcp_interfaces();
test_file_structure();
test_phpunit_config();

// Print summary.
echo "\n" . str_repeat( '=', 70 ) . "\n";
echo "📊 TEST SUMMARY\n";
echo str_repeat( '-', 70 ) . "\n";
echo "Tests run: {$tests_run}\n";
echo "Tests passed: {$tests_passed}\n";
echo 'Tests failed: ' . ( $tests_run - $tests_passed ) . "\n";

if ( $tests_passed === $tests_run ) {
	echo "\n🎉 ALL TESTS PASSED!\n";
	echo "✨ Plugin structure and dependencies are correctly configured.\n";
	echo "\n📋 What was tested:\n";
	echo "   ✅ Composer setup and autoloading\n";
	echo "   ✅ Plugin class structure\n";
	echo "   ✅ Dependency availability\n";
	echo "   ✅ Interface implementations\n";
	echo "   ✅ File structure\n";
	echo "   ✅ PHPUnit configuration\n";
	echo "\n🚀 Next steps:\n";
	echo "   • composer test:quick    - Run this test anytime\n";
	echo "   • composer test:full     - Run full WordPress tests (requires setup)\n";
	echo "   • ./run-tests.sh wp-env  - Use wp-env for full testing environment\n";
	echo "   • composer lint:php      - Check code style\n";
	echo "   • composer lint:php:stan - Run static analysis\n";
	exit( 0 );
}

echo "\n💥 SOME TESTS FAILED!\n";
echo "🔧 Please fix the following issues:\n\n";
foreach ( $test_errors as $error ) {
	echo "   ❌ {$error}\n";
}
echo "\n💡 Common solutions:\n";
echo "   • Run: composer install\n";
echo "   • Check file permissions: chmod +x run-tests.sh\n";
echo "   • Verify all required files exist\n";
echo "   • Check composer.json syntax\n";
exit( 1 );
