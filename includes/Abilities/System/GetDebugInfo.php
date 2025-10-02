<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class GetDebugInfo implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/get-debug-info',
			array(
				'label'               => 'Get Debug Info',
				'description'         => 'Retrieve WordPress debug and health information for troubleshooting.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'include_php_info' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include PHP configuration info. Default: false.',
							'default'     => false,
						),
						'include_error_log' => array(
							'type'        => 'boolean',
							'description' => 'Whether to include recent error log entries. Default: false.',
							'default'     => false,
						),
						'error_log_lines' => array(
							'type'        => 'integer',
							'description' => 'Number of recent error log lines to include. Default: 50.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 500,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'debug_status', 'health_check' ),
					'properties' => array(
						'debug_status' => array(
							'type'       => 'object',
							'properties' => array(
								'wp_debug'         => array( 'type' => 'boolean' ),
								'wp_debug_log'     => array( 'type' => 'boolean' ),
								'wp_debug_display' => array( 'type' => 'boolean' ),
								'script_debug'     => array( 'type' => 'boolean' ),
								'log_file_exists'  => array( 'type' => 'boolean' ),
								'log_file_size'    => array( 'type' => 'string' ),
								'log_file_writable' => array( 'type' => 'boolean' ),
							),
						),
						'health_check' => array(
							'type'       => 'object',
							'properties' => array(
								'overall_status' => array( 'type' => 'string' ),
								'critical_issues' => array( 'type' => 'integer' ),
								'recommended_improvements' => array( 'type' => 'integer' ),
								'good_checks'    => array( 'type' => 'integer' ),
								'tests'          => array(
									'type'  => 'array',
									'items' => array(
										'type'       => 'object',
										'properties' => array(
											'test'        => array( 'type' => 'string' ),
											'status'      => array( 'type' => 'string' ),
											'label'       => array( 'type' => 'string' ),
											'description' => array( 'type' => 'string' ),
										),
									),
								),
							),
						),
						'php_info' => array(
							'type'       => 'object',
							'properties' => array(
								'version'          => array( 'type' => 'string' ),
								'memory_limit'     => array( 'type' => 'string' ),
								'max_execution_time' => array( 'type' => 'string' ),
								'upload_max_filesize' => array( 'type' => 'string' ),
								'post_max_size'    => array( 'type' => 'string' ),
								'extensions'       => array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'error_log' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'timestamp' => array( 'type' => 'string' ),
									'level'     => array( 'type' => 'string' ),
									'message'   => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'mcp'  => ['public' => true, 'type' => 'tool'],
					'categories' => array( 'system', 'debugging' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.7,
						'readOnlyHint'    => true,
						'destructiveHint' => false,
						'idempotentHint'  => true,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for getting debug information.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the get debug info operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$include_php_info = (bool) ( $input['include_php_info'] ?? false );
		$include_error_log = (bool) ( $input['include_error_log'] ?? false );
		$error_log_lines = (int) ( $input['error_log_lines'] ?? 50 );

		$result = array();

		// Debug Status
		$log_file = \ini_get( 'error_log' );
		if ( ! $log_file && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$log_file = WP_CONTENT_DIR . '/debug.log';
		}

		$result['debug_status'] = array(
			'wp_debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'wp_debug_log'      => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
			'wp_debug_display'  => defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY,
			'script_debug'      => defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG,
			'log_file_exists'   => $log_file && file_exists( $log_file ),
			'log_file_size'     => $log_file && file_exists( $log_file ) ? \size_format( filesize( $log_file ) ) : '0 B',
			'log_file_writable' => $log_file && is_writable( dirname( $log_file ) ),
		);

		// Health Check
		$result['health_check'] = self::get_health_check_info();

		// PHP Info
		if ( $include_php_info ) {
			$result['php_info'] = array(
				'version'             => \phpversion(),
				'memory_limit'        => \ini_get( 'memory_limit' ),
				'max_execution_time'  => \ini_get( 'max_execution_time' ) . 's',
				'upload_max_filesize' => \ini_get( 'upload_max_filesize' ),
				'post_max_size'       => \ini_get( 'post_max_size' ),
				'extensions'          => \get_loaded_extensions(),
			);
		} else {
			$result['php_info'] = array();
		}

		// Error Log
		if ( $include_error_log && $log_file && file_exists( $log_file ) ) {
			$result['error_log'] = self::get_error_log_entries( $log_file, $error_log_lines );
		} else {
			$result['error_log'] = array();
		}

		return $result;
	}

	/**
	 * Get WordPress health check information.
	 *
	 * @return array Health check data.
	 */
	private static function get_health_check_info(): array {
		// Include health check functions if available
		if ( ! class_exists( 'WP_Site_Health' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		}

		$health_check = \WP_Site_Health::get_instance();
		
		// Get basic health info
		$critical_issues = 0;
		$recommended_improvements = 0;
		$good_checks = 0;
		$tests = array();

		// Try to get health check tests
		if ( method_exists( $health_check, 'get_tests' ) ) {
			$all_tests = $health_check->get_tests();
			
			// Run direct tests
			if ( isset( $all_tests['direct'] ) ) {
				foreach ( $all_tests['direct'] as $test_name => $test_data ) {
					if ( isset( $test_data['test'] ) && is_callable( $test_data['test'] ) ) {
						try {
							$test_result = call_user_func( $test_data['test'] );
							if ( is_array( $test_result ) ) {
								$status = $test_result['status'] ?? 'unknown';
								$tests[] = array(
									'test'        => $test_name,
									'status'      => $status,
									'label'       => $test_result['label'] ?? $test_name,
									'description' => $test_result['description'] ?? '',
								);

								switch ( $status ) {
									case 'critical':
										$critical_issues++;
										break;
									case 'recommended':
										$recommended_improvements++;
										break;
									case 'good':
										$good_checks++;
										break;
								}
							}
						} catch ( \Exception $e ) {
							// Skip failed tests
						}
					}
				}
			}
		}

		// Determine overall status
		$overall_status = 'good';
		if ( $critical_issues > 0 ) {
			$overall_status = 'critical';
		} elseif ( $recommended_improvements > 0 ) {
			$overall_status = 'recommended';
		}

		return array(
			'overall_status'           => $overall_status,
			'critical_issues'          => $critical_issues,
			'recommended_improvements' => $recommended_improvements,
			'good_checks'              => $good_checks,
			'tests'                    => $tests,
		);
	}

	/**
	 * Get recent error log entries.
	 *
	 * @param string $log_file Path to log file.
	 * @param int    $lines Number of lines to read.
	 * @return array Array of log entries.
	 */
	private static function get_error_log_entries( string $log_file, int $lines ): array {
		if ( ! file_exists( $log_file ) || ! is_readable( $log_file ) ) {
			return array();
		}

		$entries = array();
		$file_lines = file( $log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		
		if ( $file_lines === false ) {
			return array();
		}

		// Get the last N lines
		$recent_lines = array_slice( $file_lines, -$lines );

		foreach ( $recent_lines as $line ) {
			// Parse PHP error log format: [timestamp] PHP Level: message
			if ( preg_match( '/^\[([^\]]+)\]\s+PHP\s+([^:]+):\s+(.+)$/', $line, $matches ) ) {
				$entries[] = array(
					'timestamp' => $matches[1],
					'level'     => trim( $matches[2] ),
					'message'   => trim( $matches[3] ),
				);
			} elseif ( preg_match( '/^\[([^\]]+)\]\s+(.+)$/', $line, $matches ) ) {
				// Generic log format: [timestamp] message
				$entries[] = array(
					'timestamp' => $matches[1],
					'level'     => 'Unknown',
					'message'   => trim( $matches[2] ),
				);
			} else {
				// Fallback for unrecognized format
				$entries[] = array(
					'timestamp' => '',
					'level'     => 'Unknown',
					'message'   => $line,
				);
			}
		}

		return $entries;
	}
}
