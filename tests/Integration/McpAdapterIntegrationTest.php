<?php
/**
 * Integration tests for MCP Adapter and Abilities API working together.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;
use WP\MCP\Core\McpAdapter;

/**
 * Test MCP Adapter integration with WordPress Abilities API.
 */
final class McpAdapterIntegrationTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Initialize abilities.
		BootstrapAbilities::init();
	}

	/**
	 * Test that all example abilities are properly registered.
	 */
	public function test_all_abilities_are_registered(): void {
		$expected_abilities = array(
			'wpmcp-example/list-posts',
			'wpmcp-example/create-post',
			'wpmcp-example/get-post',
			'wpmcp-example/update-post',
			'wpmcp-example/delete-post',
			'wpmcp-example/list-block-types',
			'wpmcp-example/list-post-meta-keys',
			'wpmcp-example/get-post-meta',
			'wpmcp-example/update-post-meta',
			'wpmcp-example/delete-post-meta',
			'wpmcp-example/list-taxonomies',
			'wpmcp-example/get-terms',
			'wpmcp-example/create-term',
			'wpmcp-example/update-term',
			'wpmcp-example/delete-term',
			'wpmcp-example/attach-post-terms',
			'wpmcp-example/detach-post-terms',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertAbilityRegistered( $ability_name );
		}
	}

	/**
	 * Test that MCP adapter is properly initialized.
	 */
	public function test_mcp_adapter_is_initialized(): void {
		$this->assertTrue( McpAdapter::is_available(), 'MCP Adapter should be available' );

		$adapter = McpAdapter::instance();
		$this->assertNotNull( $adapter, 'MCP Adapter instance should be available' );

		// Trigger MCP adapter init to create the server.

		$server = $adapter->get_server( 'mcp-adapter-example-server' );
		$this->assertNotNull( $server, 'Example MCP server should be created' );
	}

	/**
	 * Test that abilities are properly exposed as MCP tools.
	 */
	public function test_abilities_exposed_as_mcp_tools(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		$this->assertNotEmpty( $tools, 'Server should have tools registered' );

		// Check that specific abilities are exposed as tools.
		$expected_tools = array(
			'wpmcp-example-list-posts',
			'wpmcp-example-create-post',
			'wpmcp-example-get-post',
			'wpmcp-example-list-block-types',
		);

		foreach ( $expected_tools as $tool_name ) {
			$tool = $server->get_tool( $tool_name );
			$this->assertNotNull( $tool, "Tool '{$tool_name}' should be registered" );
		}
	}

	/**
	 * Test MCP server configuration.
	 */
	public function test_mcp_server_configuration(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();

		$this->assertEquals( 'mcp-adapter-example-server', $server->get_server_id() );
		$this->assertEquals( 'mcp-adapter-example', $server->get_server_route_namespace() );
		$this->assertEquals( 'mcp', $server->get_server_route() );
		$this->assertEquals( 'MCP Adapter Example Server', $server->get_server_name() );
		$this->assertEquals( 'MCP server for the MCP Adapter Implementation Example plugin', $server->get_server_description() );
		$this->assertEquals( 'v1.0.0', $server->get_server_version() );
	}

	/**
	 * Test that REST API endpoints are properly registered.
	 */
	public function test_rest_api_endpoints_registered(): void {
		$adapter = $this->get_mcp_adapter();

		$server = rest_get_server();
		$routes = $server->get_routes();

		// Check that our MCP server route exists.
		$expected_route = '/mcp-adapter-example/mcp';
		$this->assertArrayHasKey( $expected_route, $routes, "REST route '{$expected_route}' should be registered" );
	}

	/**
	 * Test MCP tools/list endpoint returns expected structure.
	 */
	public function test_mcp_tools_list_endpoint(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'tools/list' );

		$this->assertEquals( 200, $response->get_status(), 'tools/list should return 200' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array' );
		$this->assertArrayHasKey( 'tools', $data, 'Response should have tools array' );
		$this->assertIsArray( $data['tools'], 'tools should be an array' );
		$this->assertNotEmpty( $data['tools'], 'tools array should not be empty' );

		// Verify tool structure.
		$first_tool = $data['tools'][0];
		$this->assertArrayHasKey( 'name', $first_tool );
		$this->assertArrayHasKey( 'description', $first_tool );
		$this->assertArrayHasKey( 'inputSchema', $first_tool );
	}

	/**
	 * Test MCP initialize endpoint returns server capabilities.
	 */
	public function test_mcp_initialize_endpoint(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'initialize' );

		$this->assertEquals( 200, $response->get_status(), 'initialize should return 200' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array' );
		$this->assertArrayHasKey( 'capabilities', $data, 'Response should have capabilities' );
		$this->assertArrayHasKey( 'serverInfo', $data, 'Response should have serverInfo' );

		// Verify server info structure.
		$server_info = $data['serverInfo'];
		$this->assertArrayHasKey( 'name', $server_info );
		$this->assertArrayHasKey( 'version', $server_info );
		$this->assertEquals( 'MCP Adapter Example Server', $server_info['name'] );
		$this->assertEquals( 'v1.0.0', $server_info['version'] );

		// Verify capabilities.
		$capabilities = $data['capabilities'];
		$this->assertArrayHasKey( 'tools', $capabilities );
	}

	/**
	 * Test ability execution through MCP tool call.
	 */
	public function test_ability_execution_through_mcp_tool(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test the list-block-types tool.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'tools/call should return 200' );

		$data = $response->get_data();
		$this->assertIsArray( $data, 'Response should be an array' );
		$this->assertArrayHasKey( 'content', $data, 'Response should have content' );

		$content = $data['content'];
		$this->assertIsArray( $content, 'Content should be an array' );
		$this->assertArrayHasKey( 'blocks', $content, 'Content should have blocks array' );
		$this->assertIsArray( $content['blocks'], 'blocks should be an array' );
	}

	/**
	 * Test error handling when calling non-existent tool.
	 */
	public function test_error_handling_for_non_existent_tool(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'non-existent-tool',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Non-existent tool should return error' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test permission handling in MCP tool execution.
	 */
	public function test_permission_handling_in_mcp_tools(): void {
		// Set up a user without permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Try to call a tool that requires higher permissions.
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

		// Should return permission error.
		$this->assertEquals( 500, $response->get_status(), 'Should return permission error' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'permission', strtolower( $data['message'] ?? '' ) );
	}

	/**
	 * Test schema validation in MCP tool execution.
	 */
	public function test_schema_validation_in_mcp_tools(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Try to call create-post with invalid arguments.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					// Missing required 'post_type' parameter.
					'title' => 'Test Post',
				),
			)
		);

		// Should return validation error.
		$this->assertEquals( 500, $response->get_status(), 'Should return validation error' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'invalid', strtolower( $data['message'] ?? '' ) );
	}

	/**
	 * Test successful post creation through MCP.
	 */
	public function test_successful_post_creation_through_mcp(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'MCP Test Post',
					'content'   => 'This post was created via MCP.',
					'status'    => 'draft',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Post creation should succeed' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertArrayHasKey( 'id', $content );
		$this->assertArrayHasKey( 'post_type', $content );
		$this->assertArrayHasKey( 'status', $content );
		$this->assertArrayHasKey( 'title', $content );

		$this->assertEquals( 'post', $content['post_type'] );
		$this->assertEquals( 'draft', $content['status'] );
		$this->assertEquals( 'MCP Test Post', $content['title'] );

		// Verify the post actually exists in WordPress.
		$post = get_post( $content['id'] );
		$this->assertNotNull( $post, 'Post should exist in WordPress' );
		$this->assertEquals( 'MCP Test Post', $post->post_title );
	}

	/**
	 * Test post listing through MCP.
	 */
	public function test_post_listing_through_mcp(): void {
		// Create some test posts.
		$post1_id = $this->create_test_post(
			array(
				'post_title'  => 'MCP Test Post 1',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$post2_id = $this->create_test_post(
			array(
				'post_title'  => 'MCP Test Post 2',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post' ),
					'post_status' => array( 'publish' ),
					'limit'       => 10,
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Post listing should succeed' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertArrayHasKey( 'posts', $content );
		$this->assertArrayHasKey( 'total', $content );
		$this->assertArrayHasKey( 'found_posts', $content );

		$posts = $content['posts'];
		$this->assertIsArray( $posts );
		$this->assertGreaterThanOrEqual( 2, count( $posts ), 'Should return at least our test posts' );

		// Verify post structure.
		$first_post = $posts[0];
		$this->assertArrayHasKey( 'id', $first_post );
		$this->assertArrayHasKey( 'post_type', $first_post );
		$this->assertArrayHasKey( 'title', $first_post );
		$this->assertArrayHasKey( 'link', $first_post );
	}

	/**
	 * Test block types listing through MCP.
	 */
	public function test_block_types_listing_through_mcp(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Block types listing should succeed' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertArrayHasKey( 'blocks', $content );

		$blocks = $content['blocks'];
		$this->assertIsArray( $blocks );
		$this->assertNotEmpty( $blocks, 'Should return available blocks' );

		// Verify block structure.
		$first_block = $blocks[0];
		$this->assertArrayHasKey( 'name', $first_block );
		$this->assertArrayHasKey( 'title', $first_block );
		$this->assertArrayHasKey( 'description', $first_block );
		$this->assertArrayHasKey( 'category', $first_block );
		$this->assertArrayHasKey( 'attributes', $first_block );
	}

	/**
	 * Test error propagation from abilities to MCP.
	 */
	public function test_error_propagation_from_abilities_to_mcp(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Try to create a post with invalid post type.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'invalid_post_type',
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid post type should return error' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test that MCP adapter properly handles WordPress ability validation.
	 */
	public function test_mcp_adapter_handles_ability_validation(): void {
		// Set up an admin user for permissions.
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with invalid input that should fail schema validation.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'limit' => 'invalid_number', // Should be integer.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid input should return error' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'invalid', strtolower( $data['message'] ?? '' ) );
	}

	/**
	 * Test that all registered tools have valid schemas.
	 */
	public function test_all_tools_have_valid_schemas(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		foreach ( $tools as $tool ) {
			$tool_array = $tool->to_array();

			// Verify required fields.
			$this->assertArrayHasKey( 'name', $tool_array );
			$this->assertArrayHasKey( 'description', $tool_array );
			$this->assertArrayHasKey( 'inputSchema', $tool_array );

			// Verify schema structure.
			$input_schema = $tool_array['inputSchema'];
			$this->assertIsArray( $input_schema );

			if ( empty( $input_schema ) ) {
				continue;
			}

			$this->assertArrayHasKey( 'type', $input_schema );
			$this->assertEquals( 'object', $input_schema['type'] );
		}
	}
}
