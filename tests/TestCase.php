<?php
/**
 * Base test case for MCP Adapter Implementation Example tests.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use WP\MCP\Core\McpAdapter;
use WP\MCP\Core\McpServer;
use WP_UnitTestCase;

/**
 * Base test case with common functionality for all tests.
 */
abstract class TestCase extends WP_UnitTestCase {
	/**
	 * MCP session ID for HTTP transport tests.
	 *
	 * @var string|null
	 */
	protected ?string $mcp_session_id = null;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Initialize abilities before anything else.
		BootstrapAbilities::init();

		if ( ! \did_action( 'init' ) ) {
			\do_action( 'init' );
		}

		$this->register_test_meta_keys();

		$adapter = null;
		if ( class_exists( McpAdapter::class ) ) {
			$adapter = McpAdapter::instance();
		}

		// Ensure abilities API is initialized after MCP adapter hooks are in place.
		if ( class_exists( 'WP_Abilities_Registry' ) ) {
			\WP_Abilities_Registry::get_instance();
		}

		$this->ensure_mcp_adapter_abilities();

		if ( ! $adapter ) {
			return;
		}

		// Initialize REST API, which will trigger the adapter's mcp_adapter_init.
		// The plugin's callback should already be registered to create the server.
		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down after each test.
	 */
	public function tear_down(): void {
		// Clean up any test abilities.
		$this->cleanup_test_abilities();
		$this->reset_abilities_registry();

		// Clean up any test posts.
		$this->cleanup_test_posts();

		// Clean up any test terms.
		$this->cleanup_test_terms();

		// Clean up MCP servers to prevent conflicts.
		$this->cleanup_mcp_servers();

		// Reset abilities bootstrap state.
		BootstrapAbilities::reset();

		$this->mcp_session_id = null;

		// Remove the mcp_adapter_init action to prevent conflicts.
		remove_all_actions( 'mcp_adapter_init' );

		parent::tear_down();
	}

	/**
	 * Register meta keys used in tests so MCP tools can read them.
	 */
	protected function register_test_meta_keys(): void {
		if ( ! function_exists( 'register_post_meta' ) ) {
			return;
		}

		$keys = array(
			'_ai_generated',
			'_creation_date',
			'_workflow_test',
			'ai_analysis_date',
			'ai_revision_count',
			'content_quality',
			'content_topics',
			'difficulty_level',
			'estimated_time',
			'priority',
			'reading_time',
			'series_name',
			'series_order',
			'test_number',
			'word_count',
		);

		foreach ( $keys as $key ) {
			register_post_meta(
				'post',
				$key,
				array(
					'type'         => 'string',
					'single'       => true,
					'show_in_rest' => true,
				)
			);
		}
	}

	/**
	 * Clean up test abilities that start with 'test/', 'core/', or 'woo/'.
	 */
	protected function cleanup_test_abilities(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		$abilities = wp_get_abilities();
		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();
			if ( ! str_starts_with( $name, 'test/' ) && ! str_starts_with( $name, 'core/' ) && ! str_starts_with( $name, 'woo/' ) ) {
				continue;
			}

			wp_unregister_ability( $name );
		}
	}

	protected function reset_abilities_registry(): void {
		foreach ( array( 'WP_Abilities_Registry', 'WP_Ability_Categories_Registry' ) as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			try {
				$reflection = new \ReflectionClass( $class_name );
				$property   = $reflection->getProperty( 'instance' );
				$property->setAccessible( true );
				$property->setValue( null, null );
			} catch ( \ReflectionException $e ) {
				continue;
			}
		}
	}

	/**
	 * Clean up test posts.
	 */
	protected function cleanup_test_posts(): void {
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_test_post',
						'value' => 'true',
					),
				),
			)
		);

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
	}

	/**
	 * Clean up test terms.
	 */
	protected function cleanup_test_terms(): void {
		$taxonomies = get_taxonomies();
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'meta_query' => array(
						array(
							'key'   => '_test_term',
							'value' => 'true',
						),
					),
				)
			);

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
		}
	}

	/**
	 * Create a test post with metadata marking it as a test post.
	 *
	 * @param array $args Post arguments.
	 * @return int|\WP_Error Post ID or error.
	 */
	protected function create_test_post( array $args = array() ): int {
		$defaults = array(
			'post_title'   => 'Test Post',
			'post_content' => 'Test content',
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'meta_input'   => array(
				'_test_post' => 'true',
			),
		);

		$args    = array_merge( $defaults, $args );
		$post_id = wp_insert_post( $args, true );

		if ( is_wp_error( $post_id ) ) {
			$this->fail( 'Failed to create test post: ' . $post_id->get_error_message() );
		}

		return $post_id;
	}

	/**
	 * Create a test term with metadata marking it as a test term.
	 *
	 * @param string $name Term name.
	 * @param string $taxonomy Taxonomy name.
	 * @param array  $args Additional arguments.
	 * @return int|\WP_Error Term ID or error.
	 */
	protected function create_test_term( string $name, string $taxonomy, array $args = array() ): int {
		$result = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Failed to create test term: ' . $result->get_error_message() );
		}

		$term_id = $result['term_id'];
		add_term_meta( $term_id, '_test_term', 'true' );

		return $term_id;
	}


	/**
	 * Get the MCP adapter instance for testing.
	 *
	 * @return \WP\MCP\Core\McpAdapter|null
	 */
	protected function get_mcp_adapter(): ?McpAdapter {
		if ( ! class_exists( McpAdapter::class ) ) {
			$this->markTestSkipped( 'MCP Adapter is not available' );
		}

		return McpAdapter::instance();
	}

	/**
	 * Get the MCP server for testing.
	 *
	 * @param string $server_id Server ID to retrieve.
	 * @return \WP\MCP\Core\McpServer|null
	 */
	protected function get_mcp_server( string $server_id = 'mcp-adapter-example-server' ): ?McpServer {
		$adapter = $this->get_mcp_adapter();
		if ( ! $adapter ) {
			return null;
		}

		return $adapter->get_server( $server_id );
	}

	/**
	 * Assert that an ability is registered.
	 *
	 * @param string $ability_name Ability name to check.
	 */
	protected function assertAbilityRegistered( string $ability_name ): void {
		$ability = wp_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability '{$ability_name}' should be registered" );
	}

	/**
	 * Assert that a tool is registered with the MCP server.
	 *
	 * @param string $tool_name Tool name to check.
	 * @param string $server_id Server ID to check.
	 */
	protected function assertToolRegistered( string $tool_name, string $server_id = 'mcp-adapter-example-server' ): void {
		$server = $this->get_mcp_server( $server_id );
		$this->assertNotNull( $server, "MCP server '{$server_id}' should exist" );

		$tools = $server->get_tools();
		$this->assertArrayHasKey( $tool_name, $tools, "Tool '{$tool_name}' should be registered with server '{$server_id}'" );
	}

	/**
	 * Execute an ability and return the result.
	 *
	 * @param string $ability_name Ability name.
	 * @param array  $input Input parameters.
	 * @return mixed Result or WP_Error.
	 */
	protected function execute_ability( string $ability_name, array $input = array() ) {
		$ability = wp_get_ability( $ability_name );
		if ( ! $ability ) {
			$this->fail( "Ability '{$ability_name}' is not registered" );
		}

		return $ability->execute( $input );
	}


	/**
	 * Debug registered REST routes.
	 */
	protected function debug_registered_routes(): void {
		$server = rest_get_server();
		$routes = $server->get_routes();
		error_log( '=== REGISTERED ROUTES ===' );
		error_log( 'Total routes: ' . count( $routes ) );
		foreach ( array_keys( $routes ) as $route ) {
			if ( strpos( $route, 'mcp' ) === false ) {
				continue;
			}

			error_log( "Found MCP route: $route" );
		}
		error_log( '=== END ROUTES ===' );
	}

	/**
	 * Debug MCP server state.
	 */
	protected function debug_mcp_server_state(): void {
		$adapter = $this->get_mcp_adapter();
		$server  = $adapter ? $adapter->get_server( 'mcp-adapter-example-server' ) : null;

		error_log( '=== MCP SERVER STATE ===' );
		error_log( 'Adapter exists: ' . ( $adapter ? 'YES' : 'NO' ) );
		error_log( 'Server exists: ' . ( $server ? 'YES' : 'NO' ) );
		if ( $server ) {
			error_log( 'Server namespace: ' . $server->get_server_route_namespace() );
			error_log( 'Server route: ' . $server->get_server_route() );
			error_log( 'Tools count: ' . count( $server->get_tools() ) );
			error_log( 'Tools: ' . implode( ', ', array_keys( $server->get_tools() ) ) );
		}
		error_log( '=== END SERVER STATE ===' );
	}

	/**
	 * Debug action hooks state.
	 */
	protected function debug_action_hooks(): void {
		global $wp_filter;
		error_log( '=== ACTION HOOKS ===' );
		error_log( 'mcp_adapter_init hooks: ' . ( isset( $wp_filter['mcp_adapter_init'] ) ? count( $wp_filter['mcp_adapter_init']->callbacks ) : 0 ) );
		error_log( 'rest_api_init hooks: ' . ( isset( $wp_filter['rest_api_init'] ) ? count( $wp_filter['rest_api_init']->callbacks ) : 0 ) );
		if ( isset( $wp_filter['rest_api_init'] ) ) {
			error_log( 'rest_api_init priorities: ' . implode( ', ', array_keys( $wp_filter['rest_api_init']->callbacks ) ) );
		}
		error_log( '=== END HOOKS ===' );
	}

	/**
	 * Clean up MCP servers to prevent conflicts between tests.
	 */
	protected function cleanup_mcp_servers(): void {
		if ( ! class_exists( McpAdapter::class ) ) {
			return;
		}

		$adapter = McpAdapter::instance();
		if ( ! $adapter ) {
			return;
		}

		// Use reflection to reset the servers array and initialization flag in the adapter.
		try {
			$reflection = new \ReflectionClass( $adapter );

			// Reset the servers array.
			$servers_property = $reflection->getProperty( 'servers' );
			$servers_property->setAccessible( true );
			$servers_property->setValue( $adapter, array() );

			// Reset adapter initialization flags so mcp_adapter_init can be called again.
			foreach ( array( 'has_triggered_init', 'initialized' ) as $property_name ) {
				if ( ! $reflection->hasProperty( $property_name ) ) {
					continue;
				}
				$init_flag_property = $reflection->getProperty( $property_name );
				$init_flag_property->setAccessible( true );
				$init_flag_property->setValue( $init_flag_property->isStatic() ? null : $adapter, false );
			}
		} catch ( \ReflectionException $e ) {
			// If reflection fails, we can't clean up servers.
			// This might cause some tests to fail, but it's better than crashing.
		}
	}

	private function ensure_mcp_adapter_abilities(): void {
		if ( ! class_exists( '\\WP\\MCP\\Abilities\\DiscoverAbilitiesAbility' ) ) {
			return;
		}

		if ( function_exists( 'wp_has_ability_category' ) && ! wp_has_ability_category( 'mcp-adapter' ) ) {
			global $wp_current_filter;
			$original_filters = $wp_current_filter;
			if ( ! is_array( $wp_current_filter ) ) {
				$wp_current_filter = array();
			}
			$wp_current_filter[] = 'wp_abilities_api_categories_init';
			wp_register_ability_category(
				'mcp-adapter',
				array(
					'label'       => 'MCP Adapter',
					'description' => 'Abilities for the MCP Adapter',
				)
			);
			array_pop( $wp_current_filter );
			$wp_current_filter = $original_filters;
		}

		$needs_register = ! wp_has_ability( 'mcp-adapter/discover-abilities' )
			|| ! wp_has_ability( 'mcp-adapter/get-ability-info' )
			|| ! wp_has_ability( 'mcp-adapter/execute-ability' );
		if ( ! $needs_register ) {
			return;
		}

		global $wp_current_filter;
		$original_filters = $wp_current_filter;
		if ( ! is_array( $wp_current_filter ) ) {
			$wp_current_filter = array();
		}
		$wp_current_filter[] = 'wp_abilities_api_init';

		if ( ! wp_has_ability( 'mcp-adapter/discover-abilities' ) ) {
			\WP\MCP\Abilities\DiscoverAbilitiesAbility::register();
		}
		if ( ! wp_has_ability( 'mcp-adapter/get-ability-info' ) ) {
			\WP\MCP\Abilities\GetAbilityInfoAbility::register();
		}
		if ( ! wp_has_ability( 'mcp-adapter/execute-ability' ) ) {
			\WP\MCP\Abilities\ExecuteAbilityAbility::register();
		}

		array_pop( $wp_current_filter );
		$wp_current_filter = $original_filters;
	}

	/**
	 * Make a mock REST request to the MCP server.
	 *
	 * @param string $method MCP method name.
	 * @param array  $params Method parameters.
	 * @param string $server_namespace Server namespace.
	 * @param string $server_route Server route.
	 * @return \WP_REST_Response|\WP_Error
	 */
	protected function make_mcp_request( string $method, array $params = array(), string $server_namespace = 'mcp-adapter-example', string $server_route = 'mcp' ) {
		// Ensure we have a current user for authentication.
		if ( ! get_current_user_id() ) {
			$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $user_id );
		}

		// Create the REST request with the correct route format.
		$route   = "/{$server_namespace}/{$server_route}";
		$request = new \WP_REST_Request( 'POST', $route );

		// Set proper headers.
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		if ( 'initialize' !== $method ) {
			$session_id = $this->ensure_mcp_session_id( $server_namespace, $server_route );
			if ( $session_id ) {
				$request->set_header( 'Mcp-Session-Id', $session_id );
			}
		}

		// Set the JSON-RPC body.
		$body = wp_json_encode(
			array(
				'jsonrpc' => '2.0',
				'method'  => $method,
				'params'  => $params,
				'id'      => 1,
			)
		);
		$request->set_body( $body );

		// Get the REST server and dispatch the request.
		$server            = rest_get_server();
		$previous_reporting = error_reporting();
		error_reporting( $previous_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );
		try {
			$response = $server->dispatch( $request );
		} finally {
			error_reporting( $previous_reporting );
		}

		$this->store_mcp_session_id( $response );
		$this->normalize_mcp_response( $response );

		return $response;
	}

	/**
	 * Ensure an MCP session exists for the server.
	 *
	 * @param string $server_namespace MCP server namespace.
	 * @param string $server_route MCP server route.
	 *
	 * @return string|null
	 */
	protected function ensure_mcp_session_id( string $server_namespace, string $server_route ): ?string {
		if ( $this->mcp_session_id ) {
			return $this->mcp_session_id;
		}

		$params = array(
			'protocolVersion' => '2025-06-18',
			'capabilities'    => array(),
			'clientInfo'      => array(
				'name'    => 'test-client',
				'version' => '1.0.0',
			),
		);

		$request = new \WP_REST_Request( 'POST', "/{$server_namespace}/{$server_route}" );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_body(
			wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'method'  => 'initialize',
					'params'  => $params,
					'id'      => 1,
				)
			)
		);

		$server   = rest_get_server();
		$response = $server->dispatch( $request );

		$this->store_mcp_session_id( $response );
		if ( ! $this->mcp_session_id && class_exists( \WP\MCP\Transport\Infrastructure\HttpSessionValidator::class ) ) {
			$session_id = \WP\MCP\Transport\Infrastructure\HttpSessionValidator::create_session( $params );
			if ( is_string( $session_id ) && '' !== $session_id ) {
				$this->mcp_session_id = $session_id;
			}
		}

		return $this->mcp_session_id;
	}

	/**
	 * Persist session ID from an MCP response.
	 *
	 * @param \WP_REST_Response $response REST response.
	 *
	 * @return void
	 */
	protected function store_mcp_session_id( \WP_REST_Response $response ): void {
		$headers = $response->get_headers();
		if ( ! is_array( $headers ) ) {
			$headers = array();
		}
		$headers    = array_change_key_case( $headers, CASE_LOWER );
		$session_id = $headers['mcp-session-id'] ?? null;
		if ( ! is_string( $session_id ) || '' === $session_id ) {
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$result = $data['result'] ?? $data;
				if ( is_object( $result ) ) {
					$result = method_exists( $result, 'toArray' ) ? $result->toArray() : (array) $result;
				}
				if ( is_array( $result ) ) {
					$session_id = $result['sessionId'] ?? $result['session_id'] ?? $session_id;
				}
			}
		}
		if ( is_string( $session_id ) && '' !== $session_id ) {
			$this->mcp_session_id = $session_id;
		}
	}

	/**
	 * Normalize MCP JSON-RPC responses to simplify assertions.
	 *
	 * @param \WP_REST_Response $response REST response.
	 *
	 * @return void
	 */
	protected function normalize_mcp_response( \WP_REST_Response $response ): void {
		$data = $response->get_data();
		if ( ! is_array( $data ) ) {
			return;
		}

		if ( isset( $data['result'] ) ) {
			$result = $data['result'];
			if ( is_object( $result ) ) {
				$result = method_exists( $result, 'toArray' ) ? $result->toArray() : (array) $result;
			}
			if ( is_array( $result ) ) {
				$result = $this->normalize_mcp_result( $result );
			}
			$response->set_data( $result );
			return;
		}

		if ( isset( $data['error'] ) ) {
			$error = $data['error'];
			$error = is_object( $error ) ? (array) $error : $error;
			$response->set_data( $error );
		}
	}

	/**
	 * Normalize MCP result payloads.
	 *
	 * @param array $result MCP result payload.
	 *
	 * @return array
	 */
	private function normalize_mcp_result( array $result ): array {
		if ( isset( $result['structuredContent'] ) && null !== $result['structuredContent'] ) {
			$structured = $result['structuredContent'];
			if ( is_object( $structured ) ) {
				$structured = (array) $structured;
			}
			if ( is_array( $structured ) ) {
				$result['content'] = $structured;
			}
		}

		if ( isset( $result['tools'] ) && is_array( $result['tools'] ) ) {
			$normalized_tools = array();
			foreach ( $result['tools'] as $tool ) {
				if ( is_object( $tool ) && method_exists( $tool, 'toArray' ) ) {
					$normalized_tools[] = $tool->toArray();
					continue;
				}
				$normalized_tools[] = is_object( $tool ) ? (array) $tool : $tool;
			}
			$result['tools'] = $normalized_tools;
		}

		return $result;
	}

	/**
	 * Extract text from MCP content blocks.
	 *
	 * @param array $content MCP content blocks.
	 *
	 * @return string
	 */
	protected function get_mcp_content_text( array $content ): string {
		if ( empty( $content ) ) {
			return '';
		}

		$first = $content[0] ?? array();
		if ( is_array( $first ) && isset( $first['text'] ) && is_string( $first['text'] ) ) {
			return $first['text'];
		}

		return '';
	}
}
