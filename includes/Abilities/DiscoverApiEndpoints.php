<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

final class DiscoverApiEndpoints implements RegistersAbility {

	private const DEFAULT_EXCLUDED_NAMESPACES = array( 'mcp', 'mcp-api-expose' );

	public static function register(): void {
		\wp_register_ability(
			'mcp-api-expose/discover-api-endpoints',
			array(
				'label'               => 'Discover REST API Endpoints',
				'description'         => 'List available WordPress REST API endpoints so the MCP Adapter can reason about them before execution.',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'endpoints' ),
					'properties' => array(
						'endpoints' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'required'   => array( 'route', 'namespace', 'methods' ),
								'properties' => array(
									'route'     => array( 'type' => 'string' ),
									'namespace' => array( 'type' => 'string' ),
									'methods'   => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
								),
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
						'priority'        => 0.65,
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
	 * Ensure the current user can enumerate endpoints.
	 *
	 * @param array $input Ability input (unused).
	 * @return bool|\WP_Error
	 */
	public static function check_permission( array $input ) {
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error( 'mcp_api_expose_auth_required', 'You must be authenticated to list REST API endpoints.' );
		}

		$capability = (string) \apply_filters( 'mcp_api_expose_discover_endpoints_capability', 'read', $input );

		if ( $capability && ! \current_user_can( $capability ) ) {
			return new \WP_Error(
				'mcp_api_expose_missing_capability',
				sprintf( 'The current user must have the "%s" capability to discover endpoints.', $capability )
			);
		}

		return true;
	}

	/**
	 * Return the list of REST API endpoints.
	 *
	 * @param array $input Ability input (unused).
	 * @return array
	 */
	public static function execute( array $input ): array {
		$server = \rest_get_server();

		if ( ! $server ) {
			return array( 'error' => 'Unable to load the WordPress REST API server.' );
		}

		$routes = $server->get_routes();

		if ( empty( $routes ) ) {
			return array( 'endpoints' => array() );
		}

		$route_data = $server->get_data_for_routes( array_keys( $routes ), 'view' );
		$excluded   = self::prepare_excluded_namespaces(
			(array) \apply_filters(
				'mcp_api_expose_discover_endpoints_excluded_namespaces',
				self::DEFAULT_EXCLUDED_NAMESPACES,
				$input
			)
		);

		$endpoints = array();

		foreach ( $route_data as $route => $data ) {
			$namespace = isset( $data['namespace'] ) ? (string) $data['namespace'] : self::infer_namespace_from_route( (string) $route );

			if ( self::should_exclude_namespace( $namespace, $excluded ) ) {
				continue;
			}

			$methods = self::normalize_methods( $data['methods'] ?? array() );

			if ( empty( $methods ) && isset( $data['endpoints'] ) && is_array( $data['endpoints'] ) ) {
				$methods = self::extract_methods_from_endpoints( $data['endpoints'] );
			}

			$endpoints[] = array(
				'route'     => (string) $route,
				'namespace' => $namespace,
				'methods'   => $methods,
			);
		}

		if ( $endpoints ) {
			usort(
				$endpoints,
				static function ( array $a, array $b ): int {
					return strcmp( $a['route'], $b['route'] );
				}
			);
		}

		return array( 'endpoints' => $endpoints );
	}

	/**
	 * Convert arbitrary namespace filter values into strings.
	 *
	 * @param array $namespaces Raw namespace list.
	 * @return array
	 */
	private static function prepare_excluded_namespaces( array $namespaces ): array {
		return array_filter(
			array_map(
				static function ( $namespace ): string {
					return strtolower( trim( (string) $namespace ) );
				},
				$namespaces
			)
		);
	}

	/**
	 * Determine if a namespace should be excluded.
	 *
	 * @param string $namespace Namespace to test.
	 * @param array  $excluded  Excluded namespaces.
	 * @return bool
	 */
	private static function should_exclude_namespace( string $namespace, array $excluded ): bool {
		if ( '' === $namespace ) {
			return false;
		}

		$namespace = strtolower( $namespace );

		foreach ( $excluded as $pattern ) {
			if ( '' === $pattern ) {
				continue;
			}

			if ( $namespace === $pattern || 0 === strpos( $namespace, $pattern . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Best-effort namespace detection for a route string.
	 *
	 * @param string $route Route.
	 * @return string
	 */
	private static function infer_namespace_from_route( string $route ): string {
		$route = trim( $route );
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
	 * Normalize a list/string of HTTP methods into a unique array.
	 *
	 * @param mixed $raw_methods Raw methods as returned by the REST server.
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
}
