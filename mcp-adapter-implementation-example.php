<?php
/**
 * Plugin Name: MCP Adapter Implementation Example
 * Description: A WordPress plugin demonstrating MCP Adapter integration and implementation patterns.
 * Version: 0.1.0
 * Author: Ovidiu Iulian Galatan (ovidiu.galatan@a8c.com)
 * Author URI: https://github.com/galatanovidiu
 * License: GPL-2.0-or-later
 * Requires PHP: 7.4
 *
 * MCP Adapter Implementation Example plugin bootstrap.
 *
 * @category Plugin
 * @package  OvidiuGalatan\McpAdapterExample
 * @author   Ovidiu Iulian Galatan <ovidiu.galatan@a8c.com>
 * @license  https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link     https://github.com/WordPress/mcp-adapter
 * @link     https://github.com/WordPress/abilities-api
 */

declare( strict_types=1 );


use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Handlers\RayMcpErrorHandler;
use OvidiuGalatan\McpAdapterExample\Handlers\RayMcpObservabilityHandler;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Transport\HttpTransport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer/Jetpack autoloader if present.
if ( is_file( __DIR__ . '/vendor/autoload_packages.php' ) ) {
	include_once __DIR__ . '/vendor/autoload_packages.php';
} elseif ( is_file( __DIR__ . '/vendor/autoload.php' ) ) {
	include_once __DIR__ . '/vendor/autoload.php';
} else {
	error_log( '[MCP Adapter Example] No autoloader found. Ensure Composer is installed and run `composer install` in the plugin directory.' );
}

// Load Abilities API (required by MCP Adapter).
if ( ! function_exists( 'wp_register_ability' ) ) {
	// Setup admin notice to inform users about missing Abilities API.
	add_action(
		'admin_notices',
		static function () {
			$plugin_url = 'https://github.com/WordPress/abilities-api';
			$message    = sprintf(
				'The <strong>MCP Adapter Implementation Example</strong> plugin requires the <strong>Abilities API</strong> plugin to be installed and activated. ' .
				'Please install it from <a href="%s" target="_blank">%s</a> or ensure it\'s available in the vendor directory.',
				esc_url( $plugin_url ),
				esc_html( $plugin_url )
			);

			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				wp_kses_post( $message )
			);
		}
	);

	return; // Exit early if Abilities API is not available.
}


add_action(
	'plugins_loaded',
	static function (): void {
		if ( ! class_exists( McpAdapter::class ) ) {
			error_log( '[MCP Adapter Example] McpAdapter class not found. Ensure MCP Adapter is loaded.' );

			return;
		}

		// Initialize the adapter.
		McpAdapter::instance();
	}
);

add_action(
/**
 * @throws \Exception
 */    'mcp_adapter_init',
	static function ( McpAdapter $adapter ): void {

		BootstrapAbilities::init();

		// Server 1: API Expose (for discovering and executing REST API endpoints)
		$adapter->create_server(
			'mcp-api-expose',
			'mcp-api-expose',
			'mcp',
			'Expose all API endpoints trough MCP',
			'Exposing all API endpoints trough MCP',
			'v1.0.0',
			array( HttpTransport::class ),
			RayMcpErrorHandler::class,
			RayMcpObservabilityHandler::class,
			array(
				'mcp-api-expose/discover-api-endpoints',
				'mcp-api-expose/get-api-endpoint-info',
				'mcp-api-expose/execute-api-endpoint',
			),
			array(),
			array()
		);

		// Server 2: Pipeline Executor (for declarative pipelines with 90%+ token reduction)
		$adapter->create_server(
			'wordpress-pipeline',
			'mcp',
			'pipeline',
			'WordPress Declarative Pipeline Executor',
			'Use this server when you need to perform multi-step WordPress operations like: batch processing content (analyzing/updating 10+ posts), complex workflows with loops and conditionals, data transformations (filtering, mapping, aggregating), error handling, or any task requiring 3+ sequential operations. Define workflows as JSON pipelines instead of making individual tool calls. Use the pipeline/get-capabilities tool to see all available operations. Best for: content migration, bulk updates, reporting, inventory management, user segmentation.',
			'v1.0.0',
			array( HttpTransport::class ),
			RayMcpErrorHandler::class,
			RayMcpObservabilityHandler::class,
			array(
				'mcp-adapter/execute-pipeline',
				'pipeline/get-capabilities',
			),
			array(
				'pipeline/examples',
			),
			array()
		);

		// Server 3: Flattened Schema Demo (for testing flat input/output handling)
		$adapter->create_server(
			'mcp-flat-schema-demo',
			'mcp',
			'flat-demo',
			'Flat Schema Demo Tools',
			'Demo tools that use flattened schemas to validate MCP adapter wrapping/unwrapping.',
			'v1.0.0',
			array( HttpTransport::class ),
			RayMcpErrorHandler::class,
			RayMcpObservabilityHandler::class,
			array(
				'test/flat-echo-string',
				'test/flat-add-ten',
				'test/flat-toggle-boolean',
				'test/flat-pick-first',
				'test/flat-random-quote',
				'test/flat-square-integer',
				'test/flat-get-post',
			),
			array(),
			array()
		);
	}
);
