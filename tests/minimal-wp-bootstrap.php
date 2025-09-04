<?php
/**
 * Minimal WordPress bootstrap for testing without database.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

// Load Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Define minimal WordPress constants.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress-core/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Mock WordPress functions needed by Jetpack autoloader.
if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( string $path ): string {
		// Simple path normalization.
		return str_replace( array( '\\', '//' ), '/', $path );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, $default = false ) {
		static $options = array();
		return $options[ $option ] ?? $default;
	}
}

if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite(): bool {
		return false;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( string $option, $default = false ) {
		return get_option( $option, $default );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		static $actions     = array();
		$actions[ $hook ][] = array( $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, ...$args ): void {
		static $actions = array();
		if ( ! isset( $actions[ $hook ] ) ) {
			return;
		}

		foreach ( $actions[ $hook ] as $action ) {
			call_user_func_array( $action[0], $args );
		}
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( array $args = array(), string $output = 'names' ): array {
		return array( 'post', 'page' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true; // For testing, assume user has all capabilities.
	}
}

// Load the plugin.
require_once __DIR__ . '/../mcp-adapter-implementation-example.php';
