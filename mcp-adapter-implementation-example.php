<?php
/**
 * Plugin Name: MCP Adapter Implementation Example
 * Description: A WordPress plugin demonstrating MCP Adapter integration and implementation patterns.
 * Version: 0.1.0
 * Author: Ovidiu Iulian Galatan (ovidiu.galatan@a8c.com)
 * Author URI: https://github.com/galatanovidiu
 * License: GPL-2.0-or-later
 * Requires PHP: 8.1
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

declare(strict_types=1);;

use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Transport\Http\RestTransport;

if (!defined('ABSPATH')) {
    exit;
}

// Load Composer/Jetpack autoloader if present.
if (is_file(__DIR__ . '/vendor/autoload_packages.php')) {
    include_once __DIR__ . '/vendor/autoload_packages.php';
} elseif (is_file(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
} else {
    error_log('[MCP Adapter Example] No autoloader found. Ensure Composer is installed and run `composer install` in the plugin directory.');
}

// Load Abilities API (required by MCP Adapter).
if (!function_exists('wp_register_ability')) {
        // Setup admin notice to inform users about missing Abilities API.
        add_action('admin_notices', static function() {
            $plugin_url = 'https://github.com/WordPress/abilities-api';
            $message = sprintf(
                'The <strong>MCP Adapter Implementation Example</strong> plugin requires the <strong>Abilities API</strong> plugin to be installed and activated. ' .
                'Please install it from <a href="%s" target="_blank">%s</a> or ensure it\'s available in the vendor directory.',
                esc_url($plugin_url),
                esc_html($plugin_url)
            );

            printf(
                '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                wp_kses_post($message)
            );
        });
        
        return; // Exit early if Abilities API is not available.
}


add_action(
    'plugins_loaded',
    static function (): void {
        if (!class_exists(McpAdapter::class)) {
            error_log('[MCP Adapter Example] McpAdapter class not found. Ensure MCP Adapter is loaded.');
            return;
        }
        
        // Attempt to initialize the adapter.
        $adapter = McpAdapter::instance();
        if (null === $adapter) {
            // Log detailed information about missing dependencies.
            $status = McpAdapter::get_dependency_status();
            error_log('[MCP Adapter Example] MCP Adapter initialization failed. Status: ' . wp_json_encode($status));
            
            // Setup admin notices to inform users about missing dependencies.
            add_action('admin_notices', static function() {
                $errors = McpAdapter::get_initialization_errors();
                if (empty($errors)) {
                    return;
                }

                $message = 'MCP Adapter Implementation Example plugin could not initialize due to missing dependencies:';
                $message .= '<ul>';
                foreach ($errors as $error) {
                    $message .= '<li>' . esc_html($error) . '</li>';
                }
                $message .= '</ul>';
                $message .= 'Please ensure all required dependencies are installed and activated.';

                printf(
                    '<div class="notice notice-error"><p><strong>MCP Adapter Example:</strong> %s</p></div>',
                    wp_kses_post($message)
                );
            });
            
            return;
        }
    }
);

add_action(
/**
 * @throws Exception
 */ 'mcp_adapter_init',
    static function (McpAdapter $adapter): void {

        // Ensure abilities are loaded prior to server creation.
        if (is_file(__DIR__ . '/src/Abilities/Bootstrap.php')) {
            include_once __DIR__ . '/src/Abilities/Bootstrap.php';
            \OvidiuGalatan\McpAdapterExample\Abilities\Bootstrap::init();
        }

        $adapter->create_server(
            'mcp-adapter-example-server',
            'mcp-adapter-example',
            'mcp',
            'MCP Adapter Example Server',
            'MCP server for the MCP Adapter Implementation Example plugin',
            'v1.0.0',
            [RestTransport::class],
            ErrorLogMcpErrorHandler::class,
            NullMcpObservabilityHandler::class,
            [
                'wpmcp-example/list-posts',
                'wpmcp-example/create-post',
                'wpmcp-example/get-post',
                'wpmcp-example/update-post',
                'wpmcp-example/delete-post',
                'wpmcp-example/list-block-types',
                'wpmcp-example/list-post-meta-keys',
                'wpmcp-example/get-post-meta',
                'wpmcp-example/update-post-meta',
                'wpmcp-example/delete-post-meta',
                'wpmcp-example/list-taxonomies',
                'wpmcp-example/get-terms',
                'wpmcp-example/create-term',
                'wpmcp-example/update-term',
                'wpmcp-example/delete-term',
                'wpmcp-example/attach-post-terms',
                'wpmcp-example/detach-post-terms',
            ],
            [],
            []
        );
        
        error_log('[MCP Adapter Example] MCP server created successfully.');
    }
);
