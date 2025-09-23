<?php
/**
 * RayMcpObservabilityHandler class for tracking MCP observability metrics using Spatie Ray.
 *
 * @package McpAdapterImplementationExample
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Handlers;

use WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;
use WP\MCP\Infrastructure\Observability\McpObservabilityHelperTrait;

/**
 * Class RayMcpObservabilityHandler
 *
 * This class handles observability tracking by sending metrics to Spatie Ray for debugging.
 * Ray provides a visual interface to track events, timings, and metrics during development.
 *
 * @package WP\MCP\ImplementationExample\Handlers
 */
class RayMcpObservabilityHandler implements McpObservabilityHandlerInterface {

	use McpObservabilityHelperTrait;

	/**
	 * Emit a countable event for tracking.
	 *
	 * @param string $event The event name to record.
	 * @param array  $tags Optional tags to attach to the event.
	 *
	 * @return void
	 */
	public static function record_event( string $event, array $tags = array() ): void {
		if ( ! function_exists( 'ray' ) ) {
			return;
		}

		$formatted_event = self::format_metric_name( $event );

		// Filter out noisy events but keep request.count for debugging
		$filtered_events = array(
			'mcp.component.registered',
			'mcp.request.duration',
			'mcp.server.created'
		);

		if ( in_array( $formatted_event, $filtered_events, true ) ) {
			return;
		}

		$merged_tags = self::merge_tags( $tags );

		$ray_data = array(
			'event' => $formatted_event,
			'tags'  => $merged_tags,
			'type'  => 'event',
		);

		// Add method to the main event data if present in tags
		if ( isset( $tags['method'] ) ) {
			$ray_data['method'] = $tags['method'];
		}

		// Expand params array if present
		if ( isset( $tags['params'] ) && is_array( $tags['params'] ) ) {
			$ray_data['params'] = $tags['params'];
		}

		// Special debugging for initialize requests
		if ( $formatted_event === 'mcp.request.count' && isset( $tags['method'] ) && $tags['method'] === 'initialize' ) {
			ray()->orange()->label( 'Initialize Request Debug' )->send( array(
				'event' => 'initialize_request_received',
				'tags' => $merged_tags,
				'full_tags' => $tags,
			) );
		}

		ray()->green()->label( 'MCP Event' )->send( $ray_data );
	}

	/**
	 * Record a timing measurement.
	 *
	 * @param string $metric The metric name for timing.
	 * @param float  $duration_ms The duration in milliseconds.
	 * @param array  $tags Optional tags to attach to the timing.
	 *
	 * @return void
	 */
	public static function record_timing( string $metric, float $duration_ms, array $tags = array() ): void {
		// Skip timing logs to reduce noise
		return;
	}

	/**
	 * Send error events to Ray with enhanced formatting.
	 *
	 * @param string     $base_event The base event name.
	 * @param \Throwable $exception The exception that occurred.
	 * @param array      $additional_tags Additional context tags.
	 *
	 * @return void
	 */
	public static function record_error_event( string $base_event, \Throwable $exception, array $additional_tags = array() ): void {
		if ( ! function_exists( 'ray' ) ) {
			return;
		}

		$error_tags = array_merge(
			array(
				'error_type'         => get_class( $exception ),
				'error_category'     => self::categorize_error( $exception ),
				'error_message_hash' => substr( md5( $exception->getMessage() ), 0, 8 ),
				'file'               => $exception->getFile(),
				'line'               => $exception->getLine(),
			),
			$additional_tags
		);

		$merged_tags = self::merge_tags( $error_tags );

		$ray_data = array(
			'event'     => self::format_metric_name( $base_event . '_failed' ),
			'exception' => array(
				'message' => $exception->getMessage(),
				'code'    => $exception->getCode(),
				'file'    => $exception->getFile(),
				'line'    => $exception->getLine(),
				'trace'   => $exception->getTraceAsString(),
			),
			'tags'      => $merged_tags,
			'type'      => 'error_event',
		);

		ray()->red()->label( 'MCP Error Event' )->send( $ray_data );
	}
}
