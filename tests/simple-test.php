<?php
/**
 * Simple test runner to verify basic functionality without full WordPress setup.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

// Load Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Basic test counter.
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
 * Test that required classes are available.
 */
function test_classes_available(): void {
	echo "\n--- Testing Class Availability ---\n";

	// Test plugin classes.
	assert_true(
		interface_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
		'RegistersAbility interface should be available'
	);

	assert_true(
		class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities' ),
		'BootstrapAbilities class should be available'
	);

	assert_true(
		class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost' ),
		'CreatePost class should be available'
	);

	assert_true(
		class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts' ),
		'ListPosts class should be available'
	);

	// Test dependency classes (if available).
	if ( class_exists( 'WP_Ability' ) ) {
		assert_true( true, 'WP_Ability class is available' );
	} else {
		echo "ℹ️  INFO: WP_Ability class not available (expected without WordPress)\n";
	}

	if ( class_exists( 'WP\MCP\Core\McpAdapter' ) ) {
		assert_true( true, 'McpAdapter class is available' );
	} else {
		echo "ℹ️  INFO: McpAdapter class not available (expected without WordPress)\n";
	}
}

/**
 * Test that Composer autoloader is working correctly.
 */
function test_autoloader(): void {
	echo "\n--- Testing Autoloader ---\n";

	// Test PSR-4 autoloading.
	$reflection    = new ReflectionClass( 'OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities' );
	$expected_file = __DIR__ . '/../includes/Abilities/BootstrapAbilities.php';

	assert_true(
		$reflection->getFileName() === $expected_file,
		'Autoloader should load classes from correct file paths'
	);
}

/**
 * Test that ability classes have required methods.
 */
function test_ability_class_structure(): void {
	echo "\n--- Testing Ability Class Structure ---\n";

	$ability_classes = array(
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost',
		'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts',
		'OvidiuGalatan\McpAdapterExample\Abilities\Blocks\ListBlockTypes',
	);

	foreach ( $ability_classes as $class_name ) {
		$reflection = new ReflectionClass( $class_name );

		assert_true(
			$reflection->implementsInterface( 'OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility' ),
			"{$class_name} should implement RegistersAbility interface"
		);

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
			$register_method->isStatic(),
			"{$class_name}::register should be static"
		);

		$check_permission_method = $reflection->getMethod( 'check_permission' );
		assert_true(
			$check_permission_method->isStatic(),
			"{$class_name}::check_permission should be static"
		);

		$execute_method = $reflection->getMethod( 'execute' );
		assert_true(
			$execute_method->isStatic(),
			"{$class_name}::execute should be static"
		);
	}
}

/**
 * Test vendor dependencies.
 */
function test_vendor_dependencies(): void {
	echo "\n--- Testing Vendor Dependencies ---\n";

	// Test PHPUnit is available.
	assert_true(
		class_exists( 'PHPUnit\Framework\TestCase' ),
		'PHPUnit should be available'
	);

	// Test wp-phpunit is available.
	assert_true(
		class_exists( 'WP_UnitTestCase' ),
		'WP_UnitTestCase should be available from wp-phpunit'
	);

	// Test polyfills are available.
	assert_true(
		class_exists( 'Yoast\PHPUnitPolyfills\TestCases\TestCase' ),
		'PHPUnit polyfills should be available'
	);
}

// Run tests.
echo "🧪 MCP Adapter Implementation Example - Simple Test Runner\n";
echo "========================================================\n";

test_classes_available();
test_autoloader();
test_ability_class_structure();
test_vendor_dependencies();

// Print summary.
echo "\n--- Test Summary ---\n";
echo "Tests run: {$tests_run}\n";
echo "Tests passed: {$tests_passed}\n";
echo 'Tests failed: ' . ( $tests_run - $tests_passed ) . "\n";

if ( $tests_passed === $tests_run ) {
	echo "\n🎉 All tests passed!\n";
	exit( 0 );
}

echo "\n💥 Some tests failed!\n";
exit( 1 );
