<?php
/**
 * Simple unit test runner that bypasses WordPress entirely.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

// Load only the basic Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Test counter.
$tests_run    = 0;
$tests_passed = 0;

/**
 * Simple assertion function.
 */
function assert_true( bool $condition, string $message ): void {
	global $tests_run, $tests_passed;
	++$tests_run;

	if ( $condition ) {
		++$tests_passed;
		echo "✅ PASS: {$message}\n";
	} else {
		echo "❌ FAIL: {$message}\n";
	}
}

/**
 * Test that all required classes are available.
 */
function test_plugin_classes_available(): void {
	echo "\n--- Testing Plugin Classes ---\n";

	// Test plugin interface.
	assert_true(
		interface_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
		'RegistersAbility interface should be available'
	);

	// Test main bootstrap class.
	assert_true(
		class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities' ),
		'BootstrapAbilities class should be available'
	);

	// Test ability classes.
	$ability_classes = array(
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\GetPost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\UpdatePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\DeletePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes',
		'OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\GetTerms',
		'OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm',
	);

	foreach ( $ability_classes as $class_name ) {
		assert_true(
			class_exists( $class_name ),
			"Class {$class_name} should be available"
		);
	}
}

/**
 * Test that dependency classes are available.
 */
function test_dependency_classes_available(): void {
	echo "\n--- Testing Dependency Classes ---\n";

	// Test Abilities API classes.
	assert_true(
		class_exists( 'WP_Ability' ),
		'WP_Ability class should be available'
	);

	assert_true(
		class_exists( 'WP_Abilities_Registry' ),
		'WP_Abilities_Registry class should be available'
	);

	// Test MCP Adapter classes.
	assert_true(
		class_exists( 'WP\MCP\Core\McpAdapter' ),
		'McpAdapter class should be available'
	);

	assert_true(
		class_exists( 'WP\MCP\Core\McpServer' ),
		'McpServer class should be available'
	);

	// Test transport classes.
	assert_true(
		class_exists( 'WP\MCP\Transport\Http\RestTransport' ),
		'RestTransport class should be available'
	);

	assert_true(
		class_exists( 'WP\MCP\Transport\Http\StreamableTransport' ),
		'StreamableTransport class should be available'
	);

	// Test error handling classes.
	assert_true(
		class_exists( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler' ),
		'ErrorLogMcpErrorHandler class should be available'
	);

	assert_true(
		class_exists( 'WP\MCP\Infrastructure\ErrorHandling\McpErrorFactory' ),
		'McpErrorFactory class should be available'
	);
}

/**
 * Test class interfaces and method signatures.
 */
function test_class_interfaces(): void {
	echo "\n--- Testing Class Interfaces ---\n";

	$ability_classes = array(
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts',
		'OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes',
	);

	foreach ( $ability_classes as $class_name ) {
		$reflection = new ReflectionClass( $class_name );

		// Test interface implementation.
		assert_true(
			$reflection->implementsInterface( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
			"{$class_name} should implement RegistersAbility interface"
		);

		// Test required methods exist.
		assert_true(
			$reflection->hasMethod( 'register' ),
			"{$class_name} should have register method"
		);

		assert_true(
			$reflection->hasMethod( 'check_permission' ),
			"{$class_name} should have check_permission method"
		);

		assert_true(
			$reflection->hasMethod( 'execute' ),
			"{$class_name} should have execute method"
		);

		// Test method signatures.
		$register_method = $reflection->getMethod( 'register' );
		assert_true(
			$register_method->isStatic() && $register_method->isPublic(),
			"{$class_name}::register should be public static"
		);

		$check_permission_method = $reflection->getMethod( 'check_permission' );
		assert_true(
			$check_permission_method->isStatic() && $check_permission_method->isPublic(),
			"{$class_name}::check_permission should be public static"
		);

		$execute_method = $reflection->getMethod( 'execute' );
		assert_true(
			$execute_method->isStatic() && $execute_method->isPublic(),
			"{$class_name}::execute should be public static"
		);
	}
}

/**
 * Test MCP Adapter interfaces.
 */
function test_mcp_interfaces(): void {
	echo "\n--- Testing MCP Interfaces ---\n";

	// Test transport interface.
	assert_true(
		interface_exists( 'WP\MCP\Transport\Contracts\McpTransportInterface' ),
		'McpTransportInterface should be available'
	);

	// Test error handler interface.
	assert_true(
		interface_exists( 'WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface' ),
		'McpErrorHandlerInterface should be available'
	);

	// Test observability interface.
	assert_true(
		interface_exists( 'WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface' ),
		'McpObservabilityHandlerInterface should be available'
	);

	// Test that implementations exist.
	assert_true(
		class_exists( 'WP\MCP\Transport\Http\RestTransport' ),
		'RestTransport implementation should be available'
	);

	// Test that RestTransport implements the interface.
	$rest_transport_reflection = new ReflectionClass( 'WP\MCP\Transport\Http\RestTransport' );
	assert_true(
		$rest_transport_reflection->implementsInterface( 'WP\MCP\Transport\Contracts\McpTransportInterface' ),
		'RestTransport should implement McpTransportInterface'
	);
}

/**
 * Test file structure.
 */
function test_file_structure(): void {
	echo "\n--- Testing File Structure ---\n";

	$required_files = array(
		'composer.json',
		'phpunit.xml.dist',
		'run-tests.sh',
		'bin/install-wp-tests.sh',
		'tests/bootstrap.php',
		'tests/TestCase.php',
		'includes/Abilities/BootstrapAbilities.php',
		'includes/Abilities/RegistersAbility.php',
	);

	foreach ( $required_files as $file ) {
		$full_path = __DIR__ . '/../' . $file;
		assert_true(
			file_exists( $full_path ),
			"Required file '{$file}' should exist"
		);
	}

	// Test directories.
	$required_directories = array(
		'tests/Unit',
		'tests/Integration',
		'tests/_output',
		'includes/Abilities/Posts',
		'includes/Abilities/Blocks',
		'includes/Abilities/Taxonomies',
	);

	foreach ( $required_directories as $dir ) {
		$full_path = __DIR__ . '/../' . $dir;
		assert_true(
			is_dir( $full_path ),
			"Required directory '{$dir}' should exist"
		);
	}
}

// Run all tests.
echo "🧪 MCP Adapter Implementation Example - Class Structure Test\n";
echo "=============================================================\n";

test_plugin_classes_available();
test_dependency_classes_available();
test_class_interfaces();
test_mcp_interfaces();
test_file_structure();

// Print summary.
echo "\n--- Test Summary ---\n";
echo "Tests run: {$tests_run}\n";
echo "Tests passed: {$tests_passed}\n";
echo 'Tests failed: ' . ( $tests_run - $tests_passed ) . "\n";

if ( $tests_passed === $tests_run ) {
	echo "\n🎉 All structure tests passed!\n";
	echo "✨ The plugin structure and class interfaces are correct.\n";
	echo "\n📝 Next steps to run full tests:\n";
	echo "   1. Set up WordPress test environment: ./run-tests.sh setup\n";
	echo "   2. Install MySQL/MariaDB for database tests\n";
	echo "   3. Run full test suite: composer test\n";
	echo "\n💡 For local development without database:\n";
	echo "   - The class structure is validated ✅\n";
	echo "   - Dependencies are properly loaded ✅\n";
	echo "   - Autoloading is working ✅\n";
	exit( 0 );
}

echo "\n💥 Some structure tests failed!\n";
echo "🔧 Please fix the failing tests before proceeding.\n";
exit( 1 );
