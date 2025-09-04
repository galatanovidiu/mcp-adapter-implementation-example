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
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Initialize abilities before anything else.
		BootstrapAbilities::init();

		// Ensure abilities API is initialized.
		do_action( 'abilities_api_init' );

		// Ensure MCP adapter is available.
		if ( ! McpAdapter::is_available() ) {
			return;
		}

		$adapter = McpAdapter::instance();
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

		// Clean up any test posts.
		$this->cleanup_test_posts();

		// Clean up any test terms.
		$this->cleanup_test_terms();

		// Clean up MCP servers to prevent conflicts.
		$this->cleanup_mcp_servers();

		// Reset abilities bootstrap state.
		BootstrapAbilities::reset();

		// Remove the mcp_adapter_init action to prevent conflicts.
		remove_all_actions( 'mcp_adapter_init' );

		parent::tear_down();
	}

	/**
	 * Clean up test abilities that start with 'test/' or 'wpmcp-example/'.
	 */
	protected function cleanup_test_abilities(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		$abilities = wp_get_abilities();
		foreach ( $abilities as $ability ) {
			$name = $ability->get_name();
			if ( ! str_starts_with( $name, 'test/' ) && ! str_starts_with( $name, 'wpmcp-example/' ) ) {
				continue;
			}

			wp_unregister_ability( $name );
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
		if ( ! McpAdapter::is_available() ) {
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

		$tool = $server->get_tool( $tool_name );
		$this->assertNotNull( $tool, "Tool '{$tool_name}' should be registered with server '{$server_id}'" );
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
		error_log('=== REGISTERED ROUTES ===');
		error_log('Total routes: ' . count($routes));
		foreach (array_keys($routes) as $route) {
			if (strpos($route, 'mcp') !== false) {
				error_log("Found MCP route: $route");
			}
		}
		error_log('=== END ROUTES ===');
	}

	/**
	 * Debug MCP server state.
	 */
	protected function debug_mcp_server_state(): void {
		$adapter = $this->get_mcp_adapter();
		$server = $adapter ? $adapter->get_server('mcp-adapter-example-server') : null;
		
		error_log('=== MCP SERVER STATE ===');
		error_log('Adapter exists: ' . ($adapter ? 'YES' : 'NO'));
		error_log('Server exists: ' . ($server ? 'YES' : 'NO'));
		if ($server) {
			error_log('Server namespace: ' . $server->get_server_route_namespace());
			error_log('Server route: ' . $server->get_server_route());
			error_log('Tools count: ' . count($server->get_tools()));
			error_log('Tools: ' . implode(', ', array_keys($server->get_tools())));
		}
		error_log('=== END SERVER STATE ===');
	}

	/**
	 * Debug action hooks state.
	 */
	protected function debug_action_hooks(): void {
		global $wp_filter;
		error_log('=== ACTION HOOKS ===');
		error_log('mcp_adapter_init hooks: ' . (isset($wp_filter['mcp_adapter_init']) ? count($wp_filter['mcp_adapter_init']->callbacks) : 0));
		error_log('rest_api_init hooks: ' . (isset($wp_filter['rest_api_init']) ? count($wp_filter['rest_api_init']->callbacks) : 0));
		if (isset($wp_filter['rest_api_init'])) {
			error_log('rest_api_init priorities: ' . implode(', ', array_keys($wp_filter['rest_api_init']->callbacks)));
		}
		error_log('=== END HOOKS ===');
	}

	/**
	 * Clean up MCP servers to prevent conflicts between tests.
	 */
	protected function cleanup_mcp_servers(): void {
		if ( ! McpAdapter::is_available() ) {
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
			
			// Reset the has_triggered_init flag so mcp_adapter_init can be called again.
			$init_flag_property = $reflection->getProperty( 'has_triggered_init' );
			$init_flag_property->setAccessible( true );
			$init_flag_property->setValue( $adapter, false );
		} catch ( \ReflectionException $e ) {
			// If reflection fails, we can't clean up servers.
			// This might cause some tests to fail, but it's better than crashing.
		}
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
		$route = "/{$server_namespace}/{$server_route}";
		$request = new \WP_REST_Request( 'POST', $route );
		
		// Set proper headers.
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		
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
		$server = rest_get_server();
		$response = $server->dispatch( $request );

		return $response;
	}
}
