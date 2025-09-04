<?php
/**
 * Minimal test runner for basic functionality testing.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

// Load minimal WordPress environment.
require_once __DIR__ . '/minimal-wp-bootstrap.php';

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;

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
 * Test basic ability registration.
 */
function test_ability_registration(): void {
	echo "\n--- Testing Ability Registration ---\n";

	// Initialize abilities.
	BootstrapAbilities::init();

	// Trigger the registration.
	do_action( 'abilities_api_init' );

	// Check that abilities were registered.
	$all_abilities = wp_get_abilities();

	assert_true(
		! empty( $all_abilities ),
		'Abilities should be registered after initialization'
	);

	// Check specific abilities.
	$expected_abilities = array(
		'wpmcp-example/create-post',
		'wpmcp-example/list-posts',
		'wpmcp-example/list-block-types',
	);

	foreach ( $expected_abilities as $ability_name ) {
		$ability = wp_get_ability( $ability_name );
		assert_true(
			$ability !== null,
			"Ability '{$ability_name}' should be registered"
		);
	}
}

/**
 * Test MCP Adapter integration (basic).
 */
function test_mcp_adapter_basic(): void {
	echo "\n--- Testing MCP Adapter Basic Integration ---\n";

	// Check if MCP Adapter classes are available.
	assert_true(
		class_exists( 'WP\MCP\Core\McpAdapter' ),
		'McpAdapter class should be available'
	);

	assert_true(
		class_exists( 'WP\MCP\Core\McpServer' ),
		'McpServer class should be available'
	);

	// Check transport classes.
	assert_true(
		class_exists( 'WP\MCP\Transport\Http\RestTransport' ),
		'RestTransport class should be available'
	);

	// Check error handling classes.
	assert_true(
		class_exists( 'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler' ),
		'ErrorLogMcpErrorHandler class should be available'
	);
}

/**
 * Test plugin structure.
 */
function test_plugin_structure(): void {
	echo "\n--- Testing Plugin Structure ---\n";

	$plugin_file = __DIR__ . '/../mcp-adapter-implementation-example.php';
	assert_true(
		file_exists( $plugin_file ),
		'Main plugin file should exist'
	);

	$composer_file = __DIR__ . '/../composer.json';
	assert_true(
		file_exists( $composer_file ),
		'composer.json should exist'
	);

	$phpunit_config = __DIR__ . '/../phpunit.xml.dist';
	assert_true(
		file_exists( $phpunit_config ),
		'phpunit.xml.dist should exist'
	);

	$test_runner = __DIR__ . '/../run-tests.sh';
	assert_true(
		file_exists( $test_runner ),
		'Test runner script should exist'
	);

	assert_true(
		is_executable( $test_runner ),
		'Test runner script should be executable'
	);
}

// Run all tests.
echo "🧪 MCP Adapter Implementation Example - Minimal Test Suite\n";
echo "==========================================================\n";

test_ability_registration();
test_mcp_adapter_basic();
test_plugin_structure();

// Print summary.
echo "\n--- Test Summary ---\n";
echo "Tests run: {$tests_run}\n";
echo "Tests passed: {$tests_passed}\n";
echo 'Tests failed: ' . ( $tests_run - $tests_passed ) . "\n";

if ( $tests_passed === $tests_run ) {
	echo "\n🎉 All basic tests passed!\n";
	echo "✨ The plugin structure and basic integration are working correctly.\n";
	echo "\n📝 Next steps:\n";
	echo "   1. Set up a WordPress test environment with database\n";
	echo "   2. Run the full PHPUnit test suite\n";
	echo "   3. Use './run-tests.sh setup' to configure the environment\n";
	exit( 0 );
}

echo "\n💥 Some basic tests failed!\n";
echo "🔧 Please fix the failing tests before proceeding.\n";
exit( 1 );
