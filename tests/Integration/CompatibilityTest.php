<?php
/**
 * Compatibility tests between abilities-api and mcp-adapter versions.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;
use WP\MCP\Core\McpAdapter;

/**
 * Test compatibility between different versions and configurations.
 */
final class CompatibilityTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test that required dependencies are available.
	 */
	public function test_required_dependencies_available(): void {
		// Test Abilities API availability.
		$this->assertTrue( function_exists( 'wp_register_ability' ), 'wp_register_ability function should be available' );
		$this->assertTrue( function_exists( 'wp_get_ability' ), 'wp_get_ability function should be available' );
		$this->assertTrue( function_exists( 'wp_get_abilities' ), 'wp_get_abilities function should be available' );
		$this->assertTrue( class_exists( 'WP_Ability' ), 'WP_Ability class should be available' );
		$this->assertTrue( class_exists( 'WP_Abilities_Registry' ), 'WP_Abilities_Registry class should be available' );

		// Test MCP Adapter availability.
		$this->assertTrue( McpAdapter::is_available(), 'MCP Adapter should be available' );
		$this->assertTrue( class_exists( 'WP\MCP\Core\McpAdapter' ), 'McpAdapter class should be available' );
		$this->assertTrue( class_exists( 'WP\MCP\Core\McpServer' ), 'McpServer class should be available' );
	}

	/**
	 * Test version compatibility.
	 */
	public function test_version_compatibility(): void {
		// Test Abilities API version.
		if ( defined( 'WP_ABILITIES_API_VERSION' ) ) {
			$this->assertNotEmpty( WP_ABILITIES_API_VERSION, 'Abilities API version should be defined' );
		}

		// Test MCP Adapter version.
		if ( defined( 'WP_MCP_VERSION' ) ) {
			$this->assertNotEmpty( WP_MCP_VERSION, 'MCP Adapter version should be defined' );
		}

		// Test that both systems can be initialized.
		$this->assertNotNull( McpAdapter::instance(), 'MCP Adapter should initialize' );
	}

	/**
	 * Test that all expected classes and interfaces are available.
	 */
	public function test_required_classes_available(): void {
		$required_classes = array(
			// Abilities API classes.
			'WP_Ability',
			'WP_Abilities_Registry',

			// MCP Adapter core classes.
			'WP\MCP\Core\McpAdapter',
			'WP\MCP\Core\McpServer',

			// MCP domain classes.
			'WP\MCP\Domain\Tools\McpTool',
			'WP\MCP\Domain\Resources\McpResource',
			'WP\MCP\Domain\Prompts\McpPrompt',

			// Transport classes.
			'WP\MCP\Transport\Http\RestTransport',
			'WP\MCP\Transport\Http\StreamableTransport',

			// Error handling classes.
			'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
			'WP\MCP\Infrastructure\ErrorHandling\McpErrorFactory',

			// Observability classes.
			'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
		);

		foreach ( $required_classes as $class_name ) {
			$this->assertTrue( class_exists( $class_name ), "Class '{$class_name}' should be available" );
		}
	}

	/**
	 * Test that required interfaces are available.
	 */
	public function test_required_interfaces_available(): void {
		$required_interfaces = array(
			'WP\MCP\Transport\Contracts\McpTransportInterface',
			'WP\MCP\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface',
			'WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface',
			'WP\MCP\Domain\Prompts\Contracts\McpPromptBuilderInterface',
		);

		foreach ( $required_interfaces as $interface_name ) {
			$this->assertTrue( interface_exists( $interface_name ), "Interface '{$interface_name}' should be available" );
		}
	}

	/**
	 * Test WordPress hooks integration.
	 */
	public function test_wordpress_hooks_integration(): void {
		// Test that required actions are available.
		$this->assertGreaterThan( 0, did_action( 'abilities_api_init' ), 'abilities_api_init action should have fired' );

		// Test MCP adapter initialization.
		$adapter = $this->get_mcp_adapter();
		$adapter->mcp_adapter_init();

		$this->assertGreaterThan( 0, did_action( 'mcp_adapter_init' ), 'mcp_adapter_init action should have fired' );
	}

	/**
	 * Test REST API integration compatibility.
	 */
	public function test_rest_api_integration_compatibility(): void {
		$adapter = $this->get_mcp_adapter();

		$server = rest_get_server();
		$this->assertNotNull( $server, 'REST server should be available' );

		$routes = $server->get_routes();
		$this->assertNotEmpty( $routes, 'REST routes should be registered' );

		// Verify our MCP route is registered.
		$mcp_route = '/mcp-adapter-example/mcp';
		$this->assertArrayHasKey( $mcp_route, $routes, 'MCP route should be registered' );
	}

	/**
	 * Test that all abilities have consistent interfaces.
	 */
	public function test_abilities_have_consistent_interfaces(): void {
		do_action( 'abilities_api_init' );

		$abilities         = wp_get_abilities();
		$example_abilities = array_filter(
			$abilities,
			static function ( $ability ) {
				return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
			}
		);

		$this->assertNotEmpty( $example_abilities, 'Should have example abilities' );

		foreach ( $example_abilities as $ability ) {
			// Test that all abilities implement required methods.
			$this->assertIsString( $ability->get_name(), 'Ability should have name' );
			$this->assertIsString( $ability->get_label(), 'Ability should have label' );
			$this->assertIsString( $ability->get_description(), 'Ability should have description' );
			$this->assertIsArray( $ability->get_input_schema(), 'Ability should have input schema' );
			$this->assertIsArray( $ability->get_output_schema(), 'Ability should have output schema' );
			$this->assertIsArray( $ability->get_meta(), 'Ability should have meta' );

			// Test that abilities can be executed.
			$this->assertTrue( method_exists( $ability, 'execute' ), 'Ability should have execute method' );
			$this->assertTrue( method_exists( $ability, 'has_permission' ), 'Ability should have has_permission method' );
		}
	}

	/**
	 * Test that MCP tools have consistent interfaces.
	 */
	public function test_mcp_tools_have_consistent_interfaces(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		$this->assertNotEmpty( $tools, 'Should have MCP tools' );

		foreach ( $tools as $tool ) {
			// Test that all tools implement required methods.
			$this->assertIsString( $tool->get_name(), 'Tool should have name' );
			$this->assertIsString( $tool->get_description(), 'Tool should have description' );
			$this->assertIsArray( $tool->get_input_schema(), 'Tool should have input schema' );

			// Test tool array representation.
			$tool_array = $tool->to_array();
			$this->assertArrayHasKey( 'name', $tool_array );
			$this->assertArrayHasKey( 'description', $tool_array );
			$this->assertArrayHasKey( 'inputSchema', $tool_array );

			// Test that tool names follow expected format.
			$this->assertStringContainsString( '-', $tool->get_name(), 'Tool name should use MCP format' );
		}
	}

	/**
	 * Test autoloader compatibility.
	 */
	public function test_autoloader_compatibility(): void {
		// Test that our plugin classes can be loaded.
		$this->assertTrue( class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities' ) );
		$this->assertTrue( class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\Posts\CreatePost' ) );
		$this->assertTrue( class_exists( 'OvidiuGalatan\McpAdapterExample\Abilities\Posts\ListPosts' ) );

		// Test that dependency classes can be loaded.
		$this->assertTrue( class_exists( 'WP_Ability' ) );
		$this->assertTrue( class_exists( 'WP\MCP\Core\McpAdapter' ) );
	}

	/**
	 * Test namespace isolation.
	 */
	public function test_namespace_isolation(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();

		// Verify server uses correct namespace.
		$this->assertEquals( 'mcp-adapter-example', $server->get_server_route_namespace() );

		// Verify tools use correct naming convention.
		$tools = $server->get_tools();
		foreach ( $tools as $tool ) {
			$this->assertStringStartsWith( 'wpmcp-example-', $tool->get_name(), 'Tools should use correct namespace prefix' );
		}
	}

	/**
	 * Test that the integration works in different WordPress environments.
	 */
	public function test_wordpress_environment_compatibility(): void {
		// Test multisite compatibility (if in multisite).
		if ( is_multisite() ) {
			$this->assertTrue( function_exists( 'wp_register_ability' ), 'Abilities API should work in multisite' );
			$this->assertTrue( McpAdapter::is_available(), 'MCP Adapter should work in multisite' );
		}

		// Test with different user capabilities.
		$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
		foreach ( $roles as $role ) {
			$user_id = $this->factory()->user->create( array( 'role' => $role ) );
			wp_set_current_user( $user_id );

			// Test that abilities respect WordPress capabilities.
			$ability = wp_get_ability( 'wpmcp-example/list-posts' );
			$this->assertNotNull( $ability );

			$has_permission = $ability->has_permission( array( 'post_type' => array( 'post' ) ) );
			$this->assertIsBool( $has_permission, "Permission check should return boolean for role '{$role}'" );
		}
	}

	/**
	 * Test plugin deactivation/reactivation compatibility.
	 */
	public function test_plugin_lifecycle_compatibility(): void {
		// Test that abilities are properly cleaned up.
		$initial_abilities = wp_get_abilities();
		$initial_count     = count(
			array_filter(
				$initial_abilities,
				static function ( $ability ) {
					return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
				}
			)
		);

		$this->assertGreaterThan( 0, $initial_count, 'Should have example abilities initially' );

		// Simulate cleanup.
		$this->cleanup_test_abilities();

		$cleaned_abilities = wp_get_abilities();
		$cleaned_count     = count(
			array_filter(
				$cleaned_abilities,
				static function ( $ability ) {
					return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
				}
			)
		);

		$this->assertEquals( 0, $cleaned_count, 'Abilities should be cleaned up' );

		// Re-register abilities.
		BootstrapAbilities::init();
		do_action( 'abilities_api_init' );

		$reregistered_abilities = wp_get_abilities();
		$reregistered_count     = count(
			array_filter(
				$reregistered_abilities,
				static function ( $ability ) {
					return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
				}
			)
		);

		$this->assertEquals( $initial_count, $reregistered_count, 'Abilities should be re-registered correctly' );
	}

	/**
	 * Test memory and resource cleanup.
	 */
	public function test_memory_and_resource_cleanup(): void {
		$initial_memory = memory_get_usage();

		// Perform multiple operations.
		$adapter = $this->get_mcp_adapter();

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->make_mcp_request( 'tools/list' );
			$this->make_mcp_request( 'initialize' );
		}

		// Clean up.
		$this->cleanup_test_abilities();
		$this->cleanup_test_posts();
		$this->cleanup_test_terms();

		$final_memory    = memory_get_usage();
		$memory_increase = $final_memory - $initial_memory;

		// Memory increase should be reasonable (under 5MB).
		$max_memory_increase = 5 * 1024 * 1024; // 5MB.
		$this->assertLessThan(
			$max_memory_increase,
			$memory_increase,
			'Memory usage should not increase excessively'
		);
	}

	/**
	 * Test WordPress core compatibility.
	 */
	public function test_wordpress_core_compatibility(): void {
		// Test that integration doesn't interfere with core WordPress functions.
		$this->assertTrue( function_exists( 'wp_insert_post' ), 'Core WordPress functions should still work' );
		$this->assertTrue( function_exists( 'get_posts' ), 'Core WordPress functions should still work' );
		$this->assertTrue( function_exists( 'wp_get_post_terms' ), 'Core WordPress functions should still work' );

		// Test that REST API still works normally.
		$server = rest_get_server();
		$routes = $server->get_routes();

		$this->assertArrayHasKey( '/wp/v2/posts', $routes, 'Core REST API routes should still be available' );
		$this->assertArrayHasKey( '/wp/v2/pages', $routes, 'Core REST API routes should still be available' );
	}

	/**
	 * Test plugin compatibility with other plugins.
	 */
	public function test_plugin_compatibility(): void {
		// Test that our abilities don't conflict with potential other abilities.
		$all_abilities = wp_get_abilities();
		$our_abilities = array_filter(
			$all_abilities,
			static function ( $ability ) {
				return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
			}
		);

		$other_abilities = array_filter(
			$all_abilities,
			static function ( $ability ) {
				return ! str_starts_with( $ability->get_name(), 'wpmcp-example/' );
			}
		);

		// Our abilities should not interfere with others.
		foreach ( $our_abilities as $our_ability ) {
			foreach ( $other_abilities as $other_ability ) {
				$this->assertNotEquals(
					$our_ability->get_name(),
					$other_ability->get_name(),
					'Ability names should not conflict'
				);
			}
		}
	}

	/**
	 * Test that the integration handles WordPress filters and actions correctly.
	 */
	public function test_wordpress_hooks_compatibility(): void {
		$adapter = $this->get_mcp_adapter();

		// Test that we can add filters without breaking the integration.
		add_filter(
			'wp_insert_post_data',
			static function ( $data ) {
				$data['post_title'] = '[FILTERED] ' . $data['post_title'];
				return $data;
			}
		);


		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data    = $response->get_data();
		$post_id = $data['content']['id'];
		$post    = get_post( $post_id );

		// Verify WordPress filters were applied.
		$this->assertStringStartsWith( '[FILTERED]', $post->post_title );

		// Remove the filter.
		remove_all_filters( 'wp_insert_post_data' );
	}

	/**
	 * Test error handling consistency across systems.
	 */
	public function test_error_handling_consistency(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test that similar errors produce consistent responses.
		$error_scenarios = array(
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array( 'post_type' => 'invalid_type' ),
			),
			array(
				'name'      => 'wpmcp-example-update-post',
				'arguments' => array( 'post_id' => 999999 ), // Non-existent post.
			),
			array(
				'name'      => 'wpmcp-example-get-post',
				'arguments' => array( 'post_id' => 999999 ), // Non-existent post.
			),
		);

		foreach ( $error_scenarios as $scenario ) {
			$response = $this->make_mcp_request( 'tools/call', $scenario );

			$this->assertEquals( 500, $response->get_status(), 'Error scenarios should return 500 status' );

			$data = $response->get_data();
			$this->assertArrayHasKey( 'code', $data, 'Error response should have code' );
			$this->assertArrayHasKey( 'message', $data, 'Error response should have message' );
			$this->assertIsString( $data['message'], 'Error message should be string' );
			$this->assertNotEmpty( $data['message'], 'Error message should not be empty' );
		}
	}

	/**
	 * Test that the integration works with WordPress caching.
	 */
	public function test_caching_compatibility(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Enable object caching simulation.
		wp_cache_flush();

		// Make the same request twice.
		$response1 = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$response2 = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $response1->get_status() );
		$this->assertEquals( 200, $response2->get_status() );

		// Results should be consistent.
		$data1 = $response1->get_data();
		$data2 = $response2->get_data();

		$this->assertEquals(
			count( $data1['content']['blocks'] ),
			count( $data2['content']['blocks'] ),
			'Cached and uncached results should be consistent'
		);
	}
}
