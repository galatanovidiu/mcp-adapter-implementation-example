<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

final class GetApiEndpointInfo implements RegistersAbility {

	public static function register(): void {
		\wp_register_ability(
			'mcp-api-expose/get-api-endpoint-info',
			array(
				'label'               => 'Get REST API Endpoint Info',
				'description'         => 'Inspect a specific WordPress REST API endpoint including methods, accepted arguments, schema, and links.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'route' ),
					'properties' => array(
						'route' => array(
							'type'        => 'string',
							'description' => 'The exact REST API route (e.g. /wp/v2/posts).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'route', 'namespace', 'methods', 'args' ),
					'properties' => array(
						'route'     => array( 'type' => 'string' ),
						'namespace' => array( 'type' => 'string' ),
						'methods'   => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
						'args'      => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'endpoints' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'methods'    => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'args'       => array(
										'type'                 => 'object',
										'additionalProperties' => true,
									),
									'allow_batch' => array( 'type' => 'boolean' ),
								),
							),
						),
						'links'    => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
						'schema'   => array(
							'oneOf' => array(
								array( 'type' => 'object' ),
								array( 'type' => 'array' ),
								array( 'type' => 'null' ),
							),
						),
					),
				),
				'permission_callback' => array( self::class, 'check_permission' ),
				'execute_callback'    => array( self::class, 'execute' ),
				'category'            => 'system',
				'meta'                => array(
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
					'annotations' => array(
						'audience'        => array( 'user', 'assistant' ),
						'priority'        => 0.66,
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
	 * Ensure the current user can read endpoint details.
	 *
	 * @param array $input Ability input.
	 * @return bool|\WP_Error
	 */
	public static function check_permission( array $input ) {
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error( 'mcp_api_expose_auth_required', 'You must be authenticated to inspect REST API endpoint metadata.' );
		}

		$capability = (string) \apply_filters( 'mcp_api_expose_get_endpoint_info_capability', 'read', $input );

		if ( $capability && ! \current_user_can( $capability ) ) {
			return new \WP_Error(
				'mcp_api_expose_missing_capability',
				sprintf( 'The current user must have the "%s" capability to inspect endpoint metadata.', $capability )
			);
		}

		return true;
	}

	/**
	 * Return endpoint data for a specific route.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute( array $input ): array {
		$route = self::normalize_route( (string) ( $input['route'] ?? '' ) );

		if ( '' === $route ) {
			return array( 'error' => 'The "route" parameter is required.' );
		}

		$server = \rest_get_server();

		if ( ! $server ) {
			return array( 'error' => 'Unable to load the WordPress REST API server.' );
		}

		$route_data = $server->get_data_for_routes( array( $route ), 'view' );

		if ( empty( $route_data ) || ! isset( $route_data[ $route ] ) ) {
			return array( 'error' => sprintf( 'No REST API route matches "%s".', $route ) );
		}

		$data      = $route_data[ $route ];
		$namespace = isset( $data['namespace'] ) ? (string) $data['namespace'] : self::infer_namespace_from_route( $route );
		$methods   = self::normalize_methods( $data['methods'] ?? array() );

		if ( empty( $methods ) && isset( $data['endpoints'] ) && is_array( $data['endpoints'] ) ) {
			$methods = self::extract_methods_from_endpoints( $data['endpoints'] );
		}

		return array(
			'route'     => $route,
			'namespace' => $namespace,
			'methods'   => $methods,
			'args'      => self::normalize_args( $data['args'] ?? array() ),
			'endpoints' => self::prepare_endpoint_details( $data['endpoints'] ?? array() ),
			'links'     => self::normalize_links( $data['_links'] ?? ( $data['links'] ?? array() ) ),
			'schema'    => $data['schema'] ?? null,
		);
	}

	/**
	 * Ensure route strings are consistently formatted.
	 *
	 * @param string $route Raw route string.
	 * @return string
	 */
	private static function normalize_route( string $route ): string {
		$route = trim( $route );

		if ( '' === $route ) {
			return '';
		}

		if ( '/' !== $route[0] ) {
			$route = '/' . ltrim( $route, '/' );
		}

		return $route;
	}

	/**
	 * Infer namespace when the REST index does not include one.
	 *
	 * @param string $route Route.
	 * @return string
	 */
	private static function infer_namespace_from_route( string $route ): string {
		$route = trim( $route, '/' );

		if ( '' === $route ) {
			return '';
		}

		$segments = explode( '/', $route );

		if ( count( $segments ) >= 2 ) {
			return $segments[0] . '/' . $segments[1];
		}

		return $segments[0];
	}

	/**
	 * Normalize HTTP methods.
	 *
	 * @param mixed $raw_methods Raw methods value.
	 * @return array
	 */
	private static function normalize_methods( $raw_methods ): array {
		$methods = array();

		if ( is_string( $raw_methods ) ) {
			$parts = preg_split( '/[\s,|]+/', strtoupper( $raw_methods ) );
			if ( $parts ) {
				$methods = array_filter( array_map( 'trim', $parts ) );
			}
		} elseif ( is_array( $raw_methods ) ) {
			foreach ( $raw_methods as $key => $value ) {
				if ( is_string( $key ) ) {
					if ( $value ) {
						$methods[] = strtoupper( $key );
					}
				} elseif ( is_string( $value ) ) {
					$methods[] = strtoupper( $value );
				}
			}
		}

		$methods = array_values( array_unique( $methods ) );
		usort( $methods, 'strcmp' );

		return $methods;
	}

	/**
	 * Extract methods from endpoint definitions.
	 *
	 * @param array $endpoints Endpoint definitions.
	 * @return array
	 */
	private static function extract_methods_from_endpoints( array $endpoints ): array {
		$methods = array();

		foreach ( $endpoints as $endpoint ) {
			if ( ! is_array( $endpoint ) ) {
				continue;
			}

			$methods = array_merge( $methods, self::normalize_methods( $endpoint['methods'] ?? array() ) );
		}

		$methods = array_values( array_unique( $methods ) );
		usort( $methods, 'strcmp' );

		return $methods;
	}

	/**
	 * Normalize argument metadata.
	 *
	 * @param mixed $args Route args.
	 * @return array
	 */
	private static function normalize_args( $args ): array {
		if ( ! is_array( $args ) ) {
			return array();
		}

		foreach ( $args as $key => $arg ) {
			if ( ! is_array( $arg ) ) {
				$args[ $key ] = array( 'description' => (string) $arg );
			}
		}

		return $args;
	}

	/**
	 * Normalize links metadata.
	 *
	 * @param mixed $links Links structure.
	 * @return array
	 */
	private static function normalize_links( $links ): array {
		if ( ! is_array( $links ) ) {
			return array();
		}

		return $links;
	}

	/**
	 * Reduce endpoint definitions to serializable data.
	 *
	 * @param array $endpoints Raw endpoints array.
	 * @return array
	 */
	private static function prepare_endpoint_details( array $endpoints ): array {
		$prepared = array();

		foreach ( $endpoints as $endpoint ) {
			if ( ! is_array( $endpoint ) ) {
				continue;
			}

			$prepared[] = array(
				'methods'     => self::normalize_methods( $endpoint['methods'] ?? array() ),
				'args'        => self::normalize_args( $endpoint['args'] ?? array() ),
				'allow_batch' => isset( $endpoint['allow_batch'] ) ? (bool) $endpoint['allow_batch'] : false,
			);
		}

		return $prepared;
	}
}
