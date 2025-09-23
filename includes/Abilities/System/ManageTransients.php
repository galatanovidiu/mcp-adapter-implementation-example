<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\System;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ManageTransients implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/manage-transients',
			array(
				'label'               => 'Manage Transients',
				'description'         => 'Manage WordPress transients - list, get, set, delete, and clean up expired transients.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'action' ),
					'properties' => array(
						'action' => array(
							'type'        => 'string',
							'description' => 'Action to perform on transients.',
							'enum'        => array( 'list', 'get', 'set', 'delete', 'cleanup' ),
						),
						'transient_name' => array(
							'type'        => 'string',
							'description' => 'Transient name for get, set, or delete actions.',
						),
						'value' => array(
							'type'        => 'string',
							'description' => 'Value to set for the transient (for set action).',
						),
						'expiration' => array(
							'type'        => 'integer',
							'description' => 'Expiration time in seconds for set action. Default: 3600 (1 hour).',
							'default'     => 3600,
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Limit number of transients to list. Default: 50.',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 500,
						),
						'filter' => array(
							'type'        => 'string',
							'description' => 'Filter transients by name pattern (for list action).',
						),
						'show_expired' => array(
							'type'        => 'boolean',
							'description' => 'Whether to show expired transients in list. Default: true.',
							'default'     => true,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'action' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'action'  => array( 'type' => 'string' ),
						'transients' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'       => array( 'type' => 'string' ),
									'value'      => array( 'type' => 'string' ),
									'expiration' => array( 'type' => 'string' ),
									'expired'    => array( 'type' => 'boolean' ),
									'size'       => array( 'type' => 'string' ),
								),
							),
						),
						'transient' => array(
							'type'       => 'object',
							'properties' => array(
								'name'       => array( 'type' => 'string' ),
								'value'      => array( 'type' => 'string' ),
								'expiration' => array( 'type' => 'string' ),
								'expired'    => array( 'type' => 'boolean' ),
								'size'       => array( 'type' => 'string' ),
							),
						),
						'cleanup_results' => array(
							'type'       => 'object',
							'properties' => array(
								'expired_cleaned'  => array( 'type' => 'integer' ),
								'orphaned_cleaned' => array( 'type' => 'integer' ),
								'total_cleaned'    => array( 'type' => 'integer' ),
								'space_freed'      => array( 'type' => 'string' ),
							),
						),
						'summary' => array(
							'type'       => 'object',
							'properties' => array(
								'total_transients' => array( 'type' => 'integer' ),
								'expired_count'    => array( 'type' => 'integer' ),
								'active_count'     => array( 'type' => 'integer' ),
							),
						),
						'message' => array( 'type' => 'string' ),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'public_mcp'  => true,
					'categories' => array( 'system', 'caching' ),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.6,
						'readOnlyHint'    => false,
						'destructiveHint' => false,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Check permission for managing transients.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the manage transients operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		$action = \sanitize_text_field( (string) $input['action'] );
		$transient_name = isset( $input['transient_name'] ) ? \sanitize_text_field( (string) $input['transient_name'] ) : '';
		$value = $input['value'] ?? '';
		$expiration = (int) ( $input['expiration'] ?? 3600 );
		$limit = (int) ( $input['limit'] ?? 50 );
		$filter = isset( $input['filter'] ) ? \sanitize_text_field( (string) $input['filter'] ) : '';
		$show_expired = (bool) ( $input['show_expired'] ?? true );

		$result = array(
			'success'         => false,
			'action'          => $action,
			'transients'      => array(),
			'transient'       => array(),
			'cleanup_results' => array(),
			'summary'         => array(),
			'message'         => '',
		);

		switch ( $action ) {
			case 'list':
				$result = array_merge( $result, self::list_transients( $limit, $filter, $show_expired ) );
				break;

			case 'get':
				if ( empty( $transient_name ) ) {
					$result['message'] = 'Transient name is required for get action.';
					break;
				}
				$result = array_merge( $result, self::get_transient( $transient_name ) );
				break;

			case 'set':
				if ( empty( $transient_name ) ) {
					$result['message'] = 'Transient name is required for set action.';
					break;
				}
				$result = array_merge( $result, self::set_transient( $transient_name, $value, $expiration ) );
				break;

			case 'delete':
				if ( empty( $transient_name ) ) {
					$result['message'] = 'Transient name is required for delete action.';
					break;
				}
				$result = array_merge( $result, self::delete_transient( $transient_name ) );
				break;

			case 'cleanup':
				$result = array_merge( $result, self::cleanup_transients() );
				break;

			default:
				$result['message'] = 'Invalid action specified.';
				break;
		}

		return $result;
	}

	/**
	 * List transients.
	 *
	 * @param int    $limit Limit number of results.
	 * @param string $filter Filter pattern.
	 * @param bool   $show_expired Whether to show expired transients.
	 * @return array Results.
	 */
	private static function list_transients( int $limit, string $filter, bool $show_expired ): array {
		global $wpdb;

		$where_clause = "WHERE option_name LIKE '_transient_%'";
		$params = array();

		if ( ! empty( $filter ) ) {
			$where_clause .= " AND option_name LIKE %s";
			$params[] = '%' . $wpdb->esc_like( $filter ) . '%';
		}

		$query = "SELECT option_name, option_value FROM {$wpdb->options} {$where_clause} ORDER BY option_name LIMIT %d";
		$params[] = $limit;

		$transient_options = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

		$transients = array();
		$expired_count = 0;
		$active_count = 0;

		foreach ( $transient_options as $option ) {
			// Skip timeout options
			if ( strpos( $option->option_name, '_transient_timeout_' ) === 0 ) {
				continue;
			}

			$transient_name = str_replace( '_transient_', '', $option->option_name );
			$timeout_option = '_transient_timeout_' . $transient_name;
			
			// Get expiration time
			$expiration_time = $wpdb->get_var( $wpdb->prepare(
				"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
				$timeout_option
			) );

			$expired = false;
			$expiration_date = 'Never';

			if ( $expiration_time ) {
				$expired = (int) $expiration_time < time();
				$expiration_date = \date( 'Y-m-d H:i:s', (int) $expiration_time );
			}

			if ( $expired ) {
				$expired_count++;
				if ( ! $show_expired ) {
					continue;
				}
			} else {
				$active_count++;
			}

			$value_size = strlen( $option->option_value );

			$transients[] = array(
				'name'       => $transient_name,
				'value'      => substr( $option->option_value, 0, 100 ) . ( $value_size > 100 ? '...' : '' ),
				'expiration' => $expiration_date,
				'expired'    => $expired,
				'size'       => \size_format( $value_size ),
			);
		}

		return array(
			'success'    => true,
			'transients' => $transients,
			'summary'    => array(
				'total_transients' => count( $transients ),
				'expired_count'    => $expired_count,
				'active_count'     => $active_count,
			),
			'message'    => sprintf( 'Found %d transients (%d active, %d expired)', count( $transients ), $active_count, $expired_count ),
		);
	}

	/**
	 * Get a specific transient.
	 *
	 * @param string $transient_name Transient name.
	 * @return array Results.
	 */
	private static function get_transient( string $transient_name ): array {
		$value = \get_transient( $transient_name );
		
		if ( $value === false ) {
			return array(
				'success' => false,
				'message' => 'Transient not found or expired.',
			);
		}

		// Get expiration info
		global $wpdb;
		$timeout_option = '_transient_timeout_' . $transient_name;
		$expiration_time = $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
			$timeout_option
		) );

		$expired = false;
		$expiration_date = 'Never';

		if ( $expiration_time ) {
			$expired = (int) $expiration_time < time();
			$expiration_date = \date( 'Y-m-d H:i:s', (int) $expiration_time );
		}

		$serialized_value = maybe_serialize( $value );
		$value_size = strlen( $serialized_value );

		return array(
			'success'   => true,
			'transient' => array(
				'name'       => $transient_name,
				'value'      => is_string( $value ) ? $value : \wp_json_encode( $value ),
				'expiration' => $expiration_date,
				'expired'    => $expired,
				'size'       => \size_format( $value_size ),
			),
			'message'   => 'Transient retrieved successfully.',
		);
	}

	/**
	 * Set a transient.
	 *
	 * @param string $transient_name Transient name.
	 * @param mixed  $value Transient value.
	 * @param int    $expiration Expiration in seconds.
	 * @return array Results.
	 */
	private static function set_transient( string $transient_name, $value, int $expiration ): array {
		$result = \set_transient( $transient_name, $value, $expiration );

		if ( $result ) {
			$expiration_date = \date( 'Y-m-d H:i:s', time() + $expiration );
			return array(
				'success'   => true,
				'transient' => array(
					'name'       => $transient_name,
					'value'      => is_string( $value ) ? $value : \wp_json_encode( $value ),
					'expiration' => $expiration_date,
					'expired'    => false,
					'size'       => \size_format( strlen( maybe_serialize( $value ) ) ),
				),
				'message'   => 'Transient set successfully.',
			);
		}

		return array(
			'success' => false,
			'message' => 'Failed to set transient.',
		);
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $transient_name Transient name.
	 * @return array Results.
	 */
	private static function delete_transient( string $transient_name ): array {
		$result = \delete_transient( $transient_name );

		return array(
			'success' => $result,
			'message' => $result ? 'Transient deleted successfully.' : 'Failed to delete transient or transient not found.',
		);
	}

	/**
	 * Clean up expired and orphaned transients.
	 *
	 * @return array Results.
	 */
	private static function cleanup_transients(): array {
		global $wpdb;

		// Clean expired transients
		$expired_transients = $wpdb->get_col( $wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < %d",
			time()
		) );

		$expired_cleaned = 0;
		foreach ( $expired_transients as $timeout_option ) {
			$transient_name = str_replace( '_transient_timeout_', '', $timeout_option );
			if ( \delete_transient( $transient_name ) ) {
				$expired_cleaned++;
			}
		}

		// Clean orphaned transient timeouts (timeouts without corresponding transients)
		$orphaned_timeouts = $wpdb->get_col(
			"SELECT t1.option_name FROM {$wpdb->options} t1 
			WHERE t1.option_name LIKE '_transient_timeout_%' 
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->options} t2 
				WHERE t2.option_name = CONCAT('_transient_', SUBSTRING(t1.option_name, 20))
			)"
		);

		$orphaned_cleaned = 0;
		foreach ( $orphaned_timeouts as $timeout_option ) {
			if ( $wpdb->delete( $wpdb->options, array( 'option_name' => $timeout_option ) ) ) {
				$orphaned_cleaned++;
			}
		}

		// Clean orphaned transients (transients without timeouts that should have them)
		$orphaned_transients = $wpdb->get_col(
			"SELECT t1.option_name FROM {$wpdb->options} t1 
			WHERE t1.option_name LIKE '_transient_%' 
			AND t1.option_name NOT LIKE '_transient_timeout_%'
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->options} t2 
				WHERE t2.option_name = CONCAT('_transient_timeout_', SUBSTRING(t1.option_name, 12))
			)"
		);

		foreach ( $orphaned_transients as $transient_option ) {
			$transient_name = str_replace( '_transient_', '', $transient_option );
			\delete_transient( $transient_name );
			$orphaned_cleaned++;
		}

		$total_cleaned = $expired_cleaned + $orphaned_cleaned;
		$space_freed = $total_cleaned * 1024; // Rough estimate

		return array(
			'success'         => true,
			'cleanup_results' => array(
				'expired_cleaned'  => $expired_cleaned,
				'orphaned_cleaned' => $orphaned_cleaned,
				'total_cleaned'    => $total_cleaned,
				'space_freed'      => \size_format( $space_freed ),
			),
			'message'         => sprintf( 'Cleanup completed: %d expired and %d orphaned transients removed', $expired_cleaned, $orphaned_cleaned ),
		);
	}
}
