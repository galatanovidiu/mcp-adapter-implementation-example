<?php
/**
 * RayMcpErrorHandler class for logging MCP errors using Spatie Ray.
 *
 * @package McpAdapterImplementationExample
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Handlers;

use WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface;

/**
 * Class RayMcpErrorHandler
 *
 * This class handles error logging by sending logs to Spatie Ray for debugging.
 * Ray provides a clean, visual debugging interface that's perfect for development.
 *
 * @package WP\MCP\ImplementationExample\Handlers
 */
class RayMcpErrorHandler implements McpErrorHandlerInterface {

	/**
	 * Log with context using Spatie Ray.
	 *
	 * @param string $message The log message.
	 * @param array  $context Additional context data.
	 * @param string $type The type of log (e.g., 'error', 'info', etc.). Default is 'error'.
	 *
	 * @return void
	 */
	public function log( string $message, array $context = array(), string $type = 'error' ): void {
		if ( ! function_exists( 'ray' ) ) {
			return;
		}

		$user_id = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;

		$ray_data = array(
			'message' => $message,
			'type'    => strtoupper( $type ),
			'user_id' => $user_id,
			'context' => $context,
		);

		// Use different Ray methods based on log type
		switch ( $type ) {
			case 'error':
				ray()->red()->label( 'MCP Error' )->send( $ray_data );
				break;
			case 'warning':
				ray()->orange()->label( 'MCP Warning' )->send( $ray_data );
				break;
			case 'info':
				ray()->blue()->label( 'MCP Info' )->send( $ray_data );
				break;
			case 'debug':
				ray()->gray()->label( 'MCP Debug' )->send( $ray_data );
				break;
			default:
				ray()->label( 'MCP Log' )->send( $ray_data );
				break;
		}
	}
}
