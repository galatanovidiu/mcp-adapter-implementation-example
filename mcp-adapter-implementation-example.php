<?php
/**
 * Plugin Name: MCP Adapter Implementation Example
 * Description: A WordPress plugin demonstrating MCP Adapter integration and implementation patterns, including the Layered Tool Pattern.
 * Version: 0.1.0
 * Author: Ovidiu Iulian Galatan (ovidiu.galatan@a8c.com)
 * Author URI: https://github.com/galatanovidiu
 * License: GPL-2.0-or-later
 * Requires PHP: 7.4
 *
 * MCP Adapter Implementation Example plugin bootstrap.
 * 
 * This plugin demonstrates two MCP server approaches:
 * 1. Traditional: Exposes all WordPress abilities as individual MCP tools (17+ tools)
 * 2. Layered: Uses the "Layered Tool Pattern" with just 3 tools (discovery → planning → execution)
 *
 * @category Plugin
 * @package  OvidiuGalatan\McpAdapterExample
 * @author   Ovidiu Iulian Galatan <ovidiu.galatan@a8c.com>
 * @license  https://www.gnu.org/licenses/gpl-2.0.html GPL-2.0-or-later
 * @link     https://github.com/WordPress/mcp-adapter
 * @link     https://github.com/WordPress/abilities-api
 * @link     https://engineering.block.xyz/blog/build-mcp-tools-like-ogres-with-layers
 */

declare( strict_types=1 );


use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Servers\LayerdMcpServer;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Core\McpServerBuilder;
use WP\MCP\Examples\CustomMcpServer;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\ErrorLogMcpObservabilityHandler;
use WP\MCP\Transport\Http\RestTransport;

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
			// Setup admin notice to inform users about missing MCP Adapter plugin.
			add_action(
				'admin_notices',
				static function () {
					$plugin_url = 'https://github.com/WordPress/mcp-adapter';
					$message    = sprintf(
						'The <strong>MCP Adapter Implementation Example</strong> plugin requires the <strong>MCP Adapter</strong> plugin to be installed and activated. ' .
						'Please install it from <a href="%s" target="_blank">%s</a> or ensure it\'s available in the mu-plugins directory.',
						esc_url( $plugin_url ),
						esc_html( $plugin_url )
					);

					printf(
						'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
						wp_kses_post( $message )
					);
				}
			);

			return; // Exit early if MCP Adapter is not available.
		}else{
			try {
				McpAdapter::instance();
			} catch ( \Exception $e ) {
				error_log( '[MCP Adapter Example] ' . $e->getMessage() );
				return;
			}
		}
	}
);

add_action(
/**
 * @throws \Exception
 */    'mcp_adapter_init',
	static function ( McpAdapter $adapter ): void {

		BootstrapAbilities::init();

		error_log( '[MCP Adapter Example] MCP Adapter has been initialized.' );

		// Create both traditional and layered servers for comparison using McpServerBuilder directly
		// 
		// McpServerBuilder provides several approaches:
		// 1. Direct instantiation: new McpServerBuilder($adapter, $server_id)
		// 2. Fluent API through adapter: $adapter->server($server_id) (returns McpServerBuilder)
		// 3. Configuration methods: configure(), configureWith(), when()
		// 4. Individual setters: addTool(), addResource(), addPrompt(), addTransport()
		// 5. Batch setters: withTools(), withResources(), withPrompts(), withTransports()
		// 6. Creation methods: create(), build(), createServerOnly()
		
		// Traditional server with all individual abilities exposed as tools
		// NOTE: Commented out due to McpAdapter validation bug - the core McpServer class
		// only has 9 required parameters but McpAdapter expects 13. This affects all servers
		// using the default McpServer class. The layered server works because it has a custom
		// server class with the correct constructor signature.
		/*
		$traditional_builder = new McpServerBuilder( $adapter, 'mcp-adapter-example-server' );
		$traditional_builder
			->namespace( 'mcp-adapter-example' )
			->route( 'mcp' )
			->name( 'MCP Adapter Example Server (Traditional)' )
			->description( 'Traditional MCP server exposing all abilities as individual tools' )
			->version( 'v1.0.0' )
			->withTransports( [ RestTransport::class ] )
			->withErrorHandler( ErrorLogMcpErrorHandler::class )
			->withObservabilityHandler( ErrorLogMcpObservabilityHandler::class )
			->withTools( [ 'wpmcp-example/list-posts', 'wpmcp-example/create-post', 'wpmcp-example/get-post', 'wpmcp-example/update-post', 'wpmcp-example/delete-post', 'wpmcp-example/list-block-types', 'wpmcp-example/list-post-meta-keys', 'wpmcp-example/get-post-meta', 'wpmcp-example/update-post-meta', 'wpmcp-example/delete-post-meta', 'wpmcp-example/list-taxonomies', 'wpmcp-example/get-terms', 'wpmcp-example/create-term', 'wpmcp-example/update-term', 'wpmcp-example/delete-term', 'wpmcp-example/attach-post-terms', 'wpmcp-example/detach-post-terms' ] )
			->withResources( [ 'wpmcp-example/post', 'wpmcp-example/block-type', 'wpmcp-example/post-meta', 'wpmcp-example/taxonomy', 'wpmcp-example/term' ] )
			->withPrompts( [ 'wpmcp-example/content-suggestions' ] )
			->create();
		*/

		// Layered server following the "Layered Tool Pattern" from Block Engineering
		// This provides just 3 tools that guide AI agents through discovery → planning → execution
		$layered_builder = new McpServerBuilder( $adapter, 'layered-abilities-server' );
		$layered_builder
			->namespace( 'mcp-layered' )
			->route( 'abilities' )
			->name( get_bloginfo( 'name' ) . ' - WordPress Abilities Server' )
			->description( 'MCP server that provides structured access to WordPress abilities for ' . get_bloginfo( 'name' ) . '.' )
			->version( '1.0.0' )
			->withTransports( [ RestTransport::class ] )
			->withErrorHandler( ErrorLogMcpErrorHandler::class )
			->withObservabilityHandler( ErrorLogMcpObservabilityHandler::class )
			->withServerClass( LayerdMcpServer::class )
			// Demonstrate conditional configuration
			->when( 
				current_user_can( 'manage_options' ), 
				function( $builder ) {
					// Only add admin-specific configuration if user has admin capabilities
					$builder->withPermissionCallback( function() {
						return current_user_can( 'edit_posts' );
					});
				}
			)
			// Demonstrate configuration callback
			->configureWith( function( $builder ) {
				// Additional configuration can be applied here
				error_log( '[MCP Adapter Example] Configuring layered server with custom settings' );
			})
			->create();
		
		error_log( '[MCP Adapter Example] Traditional MCP Server initialized at /wp-json/mcp-adapter-example/mcp/' );
		error_log( '[MCP Adapter Example] Layered MCP Server initialized at /wp-json/mcp-layered/abilities/' );
		error_log( '[MCP Adapter Example] Available layered tools: get_ability_categories, get_ability_info, execute_ability' );
	}
);
