<?php
declare(strict_types=1);

namespace OvidiuGalatan\McpAdapterExample\Abilities;

final class ExecuteApiEndpoint implements RegistersAbility {

	private const SUPPORTED_METHODS = array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD' );

	public static function register(): void {
		\wp_register_ability(
			'mcp-api-expose/execute-api-endpoint',
			array(
				'label'               => 'Execute REST API Endpoint',
				'description'         => 'Execute a WordPress REST API endpoint directly through MCP, forwarding parameters and returning the raw response.',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'route', 'method' ),
					'properties' => array(
						'route'      => array(
							'type'        => 'string',
							'description' => 'The REST API route to call (e.g. /wp/v2/posts or /wp/v2/posts/1).',
						),
						'method'     => array(
							'type'        => 'string',
							'description' => 'HTTP method to use when calling the endpoint.',
							'enum'        => self::SUPPORTED_METHODS,
						),
						'parameters' => array(
							'type'                 => 'object',
							'description'         => 'Optional query string or body parameters to send with the request.',
							'additionalProperties' => true,
							'default'              => new \stdClass(),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'success', 'data' ),
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'status'  => array( 'type' => 'integer' ),
						'data'    => array(
							'description' => 'Response payload returned by the REST endpoint.',
							'nullable'    => true,
						),
						'headers' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
						),
						'error'   => array( 'type' => 'string' ),
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
						'priority'        => 0.7,
						'readOnlyHint'    => false,
						'destructiveHint' => true,
						'idempotentHint'  => false,
						'openWorldHint'   => false,
					),
				),
			)
		);
	}

	/**
	 * Ensure the current user can execute REST requests.
	 *
	 * @param array $input Ability input.
	 * @return bool|\WP_Error
	 */
	public static function check_permission( array $input ) {
		if ( ! \is_user_logged_in() ) {
			return new \WP_Error( 'mcp_api_expose_auth_required', 'You must be authenticated to execute REST API endpoints.' );
		}

		$capability = (string) \apply_filters( 'mcp_api_expose_execute_endpoint_capability', 'read', $input );

		if ( $capability && ! \current_user_can( $capability ) ) {
			return new \WP_Error(
				'mcp_api_expose_missing_capability',
				sprintf( 'The current user must have the "%s" capability to execute REST API endpoints.', $capability )
			);
		}

		return true;
	}

	/**
	 * Execute the selected REST API endpoint.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function execute( array $input ): array {
		$route  = self::normalize_route( (string) ( $input['route'] ?? '' ) );
		$method = strtoupper( trim( (string) ( $input['method'] ?? '' ) ) );

		if ( '' === $route ) {
			return self::error_response( 'Route parameter is required before executing an endpoint.' );
		}

		if ( '' === $method ) {
			return self::error_response( 'Method parameter is required before executing an endpoint.' );
		}

		if ( ! in_array( $method, self::SUPPORTED_METHODS, true ) ) {
			return self::error_response( sprintf( 'Unsupported HTTP method "%s".', $method ) );
		}

		$parameters = isset( $input['parameters'] ) && is_array( $input['parameters'] ) ? $input['parameters'] : array();

		$server = \rest_get_server();

		if ( ! $server ) {
			return self::error_response( 'Unable to load the WordPress REST API server.' );
		}

		$request = new \WP_REST_Request( $method, $route );

		if ( ! empty( $parameters ) ) {
			if ( in_array( $method, array( 'GET', 'DELETE', 'HEAD' ), true ) ) {
				$request->set_query_params( $parameters );
			} else {
				$request->set_body_params( $parameters );
				$request->set_json_params( $parameters );
			}

			foreach ( $parameters as $key => $value ) {
				$request->set_param( (string) $key, $value );
			}
		}

		if ( ! in_array( $method, array( 'GET', 'DELETE', 'HEAD' ), true ) ) {
			$request->set_headers(
				array(
					'Content-Type' => 'application/json',
				)
			);
		}

		$response = $server->dispatch( $request );

		if ( \is_wp_error( $response ) ) {
			$error_data = $response->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 500;

			return array(
				'success' => false,
				'status'  => $status,
				'headers' => array(),
				'data'    => is_array( $error_data ) ? $error_data : null,
				'error'   => $response->get_error_message(),
			);
		}

		$data   = self::prepare_response_data( $server, $response );
		$status = (int) $response->get_status();

		return array(
			'success' => $status >= 200 && $status < 300,
			'status'  => $status,
			'headers' => $response->get_headers(),
			'data'    => $data,
			'error'   => null,
		);
	}

	/**
	 * Normalize the provided route string.
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
	 * Build a consistent error response payload.
	 *
	 * @param string $message Error message.
	 * @return array
	 */
	private static function error_response( string $message ): array {
		return array(
			'success' => false,
			'status'  => 400,
			'headers' => array(),
			'data'    => null,
			'error'   => $message,
		);
	}

	/**
	 * Convert a WP_REST_Response to array data.
	 *
	 * @param \WP_REST_Server   $server   REST server instance.
	 * @param \WP_REST_Response $response Response instance.
	 * @return mixed
	 */
	private static function prepare_response_data( $server, \WP_REST_Response $response ) {
		if ( method_exists( $server, 'response_to_data' ) ) {
			return $server->response_to_data( $response, true );
		}

		return $response->get_data();
	}
}
