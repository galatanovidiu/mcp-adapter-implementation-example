<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities\Settings;

use OvidiuGalatan\McpAdapterExample\Abilities\RegistersAbility;

final class ListSiteOptions implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'core/list-site-options',
			array(
				'label'               => 'List Site Options',
				'description'         => 'List all WordPress site options with optional filtering. Useful for discovering available settings and their current values.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => 'Search term to filter option names.',
						),
						'include_private' => array(
							'type'        => 'boolean',
							'description' => 'Include options that start with underscore (private options).',
							'default'     => false,
						),
						'include_autoload_only' => array(
							'type'        => 'boolean',
							'description' => 'Only include options that are set to autoload.',
							'default'     => false,
						),
						'exclude_serialized' => array(
							'type'        => 'boolean',
							'description' => 'Exclude options with serialized data for security.',
							'default'     => true,
						),
						'limit' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of options to return.',
							'default'     => 100,
							'minimum'     => 1,
							'maximum'     => 500,
						),
						'offset' => array(
							'type'        => 'integer',
							'description' => 'Number of options to skip (for pagination).',
							'default'     => 0,
							'minimum'     => 0,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'options', 'total' ),
					'properties' => array(
						'options' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'option_name', 'option_value', 'autoload' ),
								'properties' => array(
									'option_name'  => array( 'type' => 'string' ),
									'option_value' => array(
										'description' => 'Option value (may be string, number, boolean, or object)',
									),
									'autoload'     => array( 'type' => 'string' ),
									'is_serialized' => array( 'type' => 'boolean' ),
									'value_type'   => array( 'type' => 'string' ),
								),
							),
						),
						'total' => array(
							'type'        => 'integer',
							'description' => 'Total number of options matching the criteria',
						),
						'filtered_total' => array(
							'type'        => 'integer',
							'description' => 'Number of options returned in this request',
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'meta'                => array(
					'categories' => array( 'settings', 'options' ),
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
	 * Check permission for listing site options.
	 *
	 * @param array $input Input parameters.
	 * @return bool Whether the user has permission.
	 */
	public static function check_permission( array $input ): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Execute the list site options operation.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error Result array or error.
	 */
	public static function execute( array $input ) {
		global $wpdb;

		$search              = isset( $input['search'] ) ? \sanitize_text_field( (string) $input['search'] ) : '';
		$include_private     = ! empty( $input['include_private'] );
		$include_autoload_only = ! empty( $input['include_autoload_only'] );
		$exclude_serialized  = array_key_exists( 'exclude_serialized', $input ) ? (bool) $input['exclude_serialized'] : true;
		$limit               = isset( $input['limit'] ) ? max( 1, min( 500, (int) $input['limit'] ) ) : 100;
		$offset              = isset( $input['offset'] ) ? max( 0, (int) $input['offset'] ) : 0;

		// Build the WHERE clause
		$where_conditions = array();
		$where_values     = array();

		// Exclude private options unless explicitly included
		if ( ! $include_private ) {
			$where_conditions[] = 'option_name NOT LIKE %s';
			$where_values[]     = $wpdb->esc_like( '_' ) . '%';
		}

		// Filter by autoload if requested
		if ( $include_autoload_only ) {
			$where_conditions[] = 'autoload = %s';
			$where_values[]     = 'yes';
		}

		// Search filter
		if ( ! empty( $search ) ) {
			$where_conditions[] = 'option_name LIKE %s';
			$where_values[]     = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$where_clause = ! empty( $where_conditions ) ? 'WHERE ' . implode( ' AND ', $where_conditions ) : '';

		// Get total count
		$count_query = "SELECT COUNT(*) FROM {$wpdb->options} {$where_clause}";
		if ( ! empty( $where_values ) ) {
			$count_query = $wpdb->prepare( $count_query, ...$where_values );
		}
		$total = (int) $wpdb->get_var( $count_query );

		// Get the options
		$options_query = "SELECT option_name, option_value, autoload FROM {$wpdb->options} {$where_clause} ORDER BY option_name LIMIT %d OFFSET %d";
		$query_values  = array_merge( $where_values, array( $limit, $offset ) );
		$options_query = $wpdb->prepare( $options_query, ...$query_values );
		$raw_options   = $wpdb->get_results( $options_query );

		if ( ! $raw_options ) {
			return array(
				'options'        => array(),
				'total'          => 0,
				'filtered_total' => 0,
			);
		}

		$processed_options = array();
		foreach ( $raw_options as $option ) {
			$is_serialized = \is_serialized( $option->option_value );

			// Skip serialized data if requested
			if ( $exclude_serialized && $is_serialized ) {
				continue;
			}

			$processed_value = $option->option_value;
			$value_type      = 'string';

			// Process the value based on its type
			if ( $is_serialized ) {
				if ( \is_serialized_string( $option->option_value ) ) {
					$processed_value = \maybe_unserialize( $option->option_value );
					$value_type      = 'serialized_string';
				} else {
					$processed_value = 'SERIALIZED_DATA';
					$value_type      = 'serialized_complex';
				}
			} else {
				// Try to detect the actual type
				if ( \is_numeric( $option->option_value ) ) {
					if ( \is_int( $option->option_value + 0 ) ) {
						$processed_value = (int) $option->option_value;
						$value_type      = 'integer';
					} else {
						$processed_value = (float) $option->option_value;
						$value_type      = 'float';
					}
				} elseif ( \in_array( $option->option_value, array( '0', '1' ), true ) ) {
					$processed_value = (bool) $option->option_value;
					$value_type      = 'boolean';
				} elseif ( empty( $option->option_value ) ) {
					$value_type = 'empty';
				}
			}

			$processed_options[] = array(
				'option_name'   => $option->option_name,
				'option_value'  => $processed_value,
				'autoload'      => $option->autoload,
				'is_serialized' => $is_serialized,
				'value_type'    => $value_type,
			);
		}

		return array(
			'options'        => $processed_options,
			'total'          => $total,
			'filtered_total' => count( $processed_options ),
		);
	}
}
