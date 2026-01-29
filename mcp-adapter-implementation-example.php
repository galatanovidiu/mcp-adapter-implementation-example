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
	'mcp_adapter_init',
	/**
	 * @throws \Exception
	 */
	static function ( McpAdapter $adapter ): void {
		BootstrapAbilities::init();

		// Full WordPress MCP Server with all tools, resources, and prompts
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$adapter->create_server(
			'wordpress-full',
			'mcp',
			'full',
			sprintf( '%s - %s', $site_name, $site_url ),
			sprintf(
				'MCP server for "%s" (%s). Complete WordPress management with tools for content, media, users, plugins, themes, menus, comments, settings, system management, security, and WooCommerce operations.',
				$site_name,
				$site_url
			),
			'v1.0.0',
			array( HttpTransport::class ),
			RayMcpErrorHandler::class,
			RayMcpObservabilityHandler::class,
			// Tools
			array(
				// Posts
				'core/create-post',
				'core/get-post',
				'core/list-posts',
				'core/update-post',
				'core/delete-post',
				// Post Meta
				'core/list-post-meta-keys',
				'core/get-post-meta',
				'core/update-post-meta',
				'core/delete-post-meta',
				// Blocks
				'core/list-block-types',
				// Taxonomies & Terms
				'core/list-taxonomies',
				'core/get-terms',
				'core/create-term',
				'core/update-term',
				'core/delete-term',
				'core/attach-post-terms',
				'core/detach-post-terms',
				// Settings
				'core/get-site-settings',
				'core/update-site-settings',
				'core/list-site-options',
				// Plugins
				'core/list-plugins',
				'core/get-plugin-info',
				'core/activate-plugin',
				'core/deactivate-plugin',
				'core/install-plugin',
				'core/delete-plugin',
				// Users
				'core/list-users',
				'core/get-user',
				'core/create-user',
				'core/update-user',
				'core/delete-user',
				'core/get-user-meta',
				'core/update-user-meta',
				'core/change-user-role',
				// Media
				'core/list-media',
				'core/get-attachment',
				'core/upload-media',
				'core/update-attachment',
				'core/delete-attachment',
				'core/get-media-sizes',
				'core/generate-image-sizes',
				// Themes
				'core/list-themes',
				'core/get-theme-info',
				'core/activate-theme',
				'core/install-theme',
				'core/delete-theme',
				'core/get-theme-customizer',
				// Comments
				'core/list-comments',
				'core/get-comment',
				'core/create-comment',
				'core/update-comment',
				'core/delete-comment',
				'core/approve-comment',
				'core/get-comment-meta',
				// Menus
				'core/list-menus',
				'core/get-menu',
				'core/create-menu',
				'core/update-menu',
				'core/delete-menu',
				'core/get-menu-locations',
				'core/assign-menu-location',
				// System
				'core/get-system-info',
				'core/check-updates',
				'core/run-updates',
				'core/optimize-database',
				'core/get-debug-info',
				'core/manage-transients',
				'core/get-constants',
				// Security
				'core/check-file-permissions',
				'core/scan-malware',
				'core/update-salts',
				// WooCommerce Products
				'woo/list-products',
				'woo/get-product',
				'woo/create-product',
				'woo/update-product',
				'woo/delete-product',
				'woo/duplicate-product',
				// WooCommerce Store
				'woo/get-store-settings',
				'woo/get-store-status',
				'woo/get-store-info',
				'woo/update-store-settings',
				'woo/manage-payment-methods',
				'woo/manage-shipping-methods',
				// WooCommerce Variations
				'woo/list-product-variations',
				'woo/get-product-variation',
				'woo/create-product-variation',
				'woo/update-product-variation',
				'woo/delete-product-variation',
				// WooCommerce Attributes
				'woo/list-product-attributes',
				'woo/create-product-attribute',
				'woo/update-product-attribute',
				// WooCommerce Categories
				'woo/list-product-categories',
				'woo/get-product-category',
				'woo/create-product-category',
				'woo/update-product-category',
				'woo/delete-product-category',
				// WooCommerce Tags
				'woo/list-product-tags',
				'woo/manage-product-tags',
			),
			// Resources
			array(
				'resources/posts-list',
				'resources/site-settings',
				// UI Resources for MCP Apps
				'core/list-posts-ui',
			),
			// Prompts
			array(
				'prompts/generate-post',
				'prompts/summarize-content',
			)
		);
	}
);
