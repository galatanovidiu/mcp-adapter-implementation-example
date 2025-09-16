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
use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
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

		// Attempt to initialize the adapter.
		$adapter = McpAdapter::instance();
	}
);

add_action(
/**
 * @throws \Exception
 */    'mcp_adapter_init',
	static function ( McpAdapter $adapter ): void {

			BootstrapAbilities::init();

		$adapter->create_server(
			'mcp-adapter-example-server',
			'mcp-example',
			'mcp',
			'MCP Adapter Example Server',
			'MCP server for the MCP Adapter Implementation Example plugin',
			'v1.0.0',
			array( HttpTransport::class ),
			ErrorLogMcpErrorHandler::class,
			NullMcpObservabilityHandler::class,
			array(
				// Core WordPress abilities
				'core/activate-plugin',
				'core/activate-theme',
				'core/approve-comment',
				'core/assign-menu-location',
				'core/attach-post-terms',
				'core/change-user-role',
				'core/check-file-permissions',
				'core/check-updates',
				'core/create-comment',
				'core/create-menu',
				'core/create-post',
				'core/create-term',
				'core/create-user',
				'core/deactivate-plugin',
				'core/delete-attachment',
				'core/delete-comment',
				'core/delete-menu',
				'core/delete-plugin',
				'core/delete-post',
				'core/delete-post-meta',
				'core/delete-term',
				'core/delete-theme',
				'core/delete-user',
				'core/detach-post-terms',
				'core/generate-image-sizes',
				'core/get-attachment',
				'core/get-comment',
				'core/get-comment-meta',
				'core/get-constants',
				'core/get-debug-info',
				'core/get-media-sizes',
				'core/get-menu',
				'core/get-menu-locations',
				'core/get-plugin-info',
				'core/get-post',
				'core/get-post-meta',
				'core/get-site-settings',
				'core/get-system-info',
				'core/get-terms',
				'core/get-theme-customizer',
				'core/get-theme-info',
				'core/get-user',
				'core/get-user-meta',
				'core/install-plugin',
				'core/install-theme',
				'core/list-block-types',
				'core/list-comments',
				'core/list-media',
				'core/list-menus',
				'core/list-plugins',
				'core/list-post-meta-keys',
				'core/list-posts',
				'core/list-site-options',
				'core/list-taxonomies',
				'core/list-themes',
				'core/list-users',
				'core/manage-transients',
				'core/optimize-database',
				'core/run-updates',
				'core/scan-malware',
				'core/update-attachment',
				'core/update-comment',
				'core/update-menu',
				'core/update-post',
				'core/update-post-meta',
				'core/update-salts',
				'core/update-site-settings',
				'core/update-term',
				'core/update-user',
				'core/update-user-meta',
				'core/upload-media',
				
				// WooCommerce abilities
				// 'woo/create-product',
				// 'woo/create-product-attribute',
				// 'woo/create-product-category',
				// 'woo/create-product-variation',
				// 'woo/delete-product',
				// 'woo/delete-product-category',
				// 'woo/delete-product-variation',
				// 'woo/duplicate-product',
				// 'woo/get-product',
				// 'woo/get-product-category',
				// 'woo/get-product-variation',
				// 'woo/get-store-info',
				// 'woo/get-store-settings',
				// 'woo/get-store-status',
				// 'woo/list-product-attributes',
				// 'woo/list-product-categories',
				// 'woo/list-product-tags',
				// 'woo/list-product-variations',
				// 'woo/list-products',
				// 'woo/manage-product-tags',
				// 'woo/update-product',
				// 'woo/update-product-attribute',
				// 'woo/update-product-category',
				// 'woo/update-product-variation',
			),
			array(),
			array()
		);
	}
);
