<?php
/**
 * Bootstrap the PHPUnit tests for MCP Adapter Implementation Example.
 *
 * @package OvidiuGalatan\McpAdapterExample
 */

declare( strict_types=1 );

// Define test constants.
define( 'TESTS_REPO_ROOT_DIR', dirname( __DIR__ ) );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', TESTS_REPO_ROOT_DIR . '/vendor/yoast/phpunit-polyfills' );

// Load Composer dependencies.
if ( file_exists( TESTS_REPO_ROOT_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_REPO_ROOT_DIR . '/vendor/autoload.php';
}

// Use wp-phpunit for simpler testing without full WordPress setup.
$_test_root = TESTS_REPO_ROOT_DIR . '/vendor/wp-phpunit/wp-phpunit';

// Give access to tests_add_filter() function.
require_once $_test_root . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	// Load the main plugin (which includes its own dependency loading).
	require_once TESTS_REPO_ROOT_DIR . '/mcp-adapter-implementation-example.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
