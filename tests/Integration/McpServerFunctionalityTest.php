<?php
/**
 * Integration tests for MCP server functionality.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test MCP server functionality and REST API endpoints.
 */
final class McpServerFunctionalityTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test MCP server initialization.
	 */
	public function test_mcp_server_initialization(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$this->assertNotNull( $server, 'MCP server should be initialized' );

		// Verify server properties.
		$this->assertEquals( 'mcp-adapter-example-server', $server->get_server_id() );
		$this->assertEquals( 'mcp-adapter-example', $server->get_server_route_namespace() );
		$this->assertEquals( 'mcp', $server->get_server_route() );
		$this->assertEquals( 'MCP Adapter Example Server', $server->get_server_name() );
	}

	/**
	 * Test tools registration in MCP server.
	 */
	public function test_tools_registration(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		$this->assertNotEmpty( $tools, 'Server should have tools registered' );

		$expected_tools = array(
			'wpmcp-example-list-posts',
			'wpmcp-example-create-post',
			'wpmcp-example-get-post',
			'wpmcp-example-update-post',
			'wpmcp-example-delete-post',
			'wpmcp-example-list-block-types',
		);

		foreach ( $expected_tools as $tool_name ) {
			$tool = $server->get_tool( $tool_name );
			$this->assertNotNull( $tool, "Tool '{$tool_name}' should be registered" );
		}
	}

	/**
	 * Test MCP initialize endpoint.
	 */
	public function test_initialize_endpoint(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'initialize',
			array(
				'protocolVersion' => '2024-11-05',
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'test-client',
					'version' => '1.0.0',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'protocolVersion', $data );
		$this->assertArrayHasKey( 'capabilities', $data );
		$this->assertArrayHasKey( 'serverInfo', $data );

		// Verify server info.
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
	 * Test tools/list endpoint.
	 */
	public function test_tools_list_endpoint(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'tools/list' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'tools', $data );

		$tools = $data['tools'];
		$this->assertIsArray( $tools );
		$this->assertNotEmpty( $tools );

		// Verify each tool has required fields.
		foreach ( $tools as $tool ) {
			$this->assertArrayHasKey( 'name', $tool );
			$this->assertArrayHasKey( 'description', $tool );
			$this->assertArrayHasKey( 'inputSchema', $tool );

			$this->assertIsString( $tool['name'] );
			$this->assertIsString( $tool['description'] );
			$this->assertIsArray( $tool['inputSchema'] );
		}
	}

	/**
	 * Test tools/call endpoint with valid tool.
	 */
	public function test_tools_call_endpoint_success(): void {
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

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertIsArray( $content );
		$this->assertArrayHasKey( 'blocks', $content );
	}

	/**
	 * Test tools/call endpoint with invalid tool.
	 */
	public function test_tools_call_endpoint_invalid_tool(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'non-existent-tool',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'not found', strtolower( $data['message'] ) );
	}

	/**
	 * Test tools/call endpoint with missing parameters.
	 */
	public function test_tools_call_endpoint_missing_parameters(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				// Missing 'name' parameter.
				'arguments' => array(),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'missing', strtolower( $data['message'] ) );
	}

	/**
	 * Test tools/call endpoint with permission denied.
	 */
	public function test_tools_call_endpoint_permission_denied(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

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

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'permission', strtolower( $data['message'] ?? '' ) );
	}

	/**
	 * Test invalid JSON-RPC request format.
	 */
	public function test_invalid_jsonrpc_request_format(): void {
		$adapter = $this->get_mcp_adapter();

		// Make request without required fields.
		$request = new \WP_REST_Request( 'POST', '/wp-json/mcp-adapter-example/mcp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					// Missing 'method' field.
					'params' => array(),
					'id'     => 1,
				)
			)
		);

		$server   = rest_get_server();
		$response = $server->dispatch( $request );

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
	}

	/**
	 * Test ping endpoint.
	 */
	public function test_ping_endpoint(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'ping' );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		// Ping should return empty object/array.
		$this->assertEmpty( $data );
	}

	/**
	 * Test method not found handling.
	 */
	public function test_method_not_found(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'nonexistent/method' );

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'method not found', strtolower( $data['message'] ) );
	}

	/**
	 * Test complex tool execution with multiple parameters.
	 */
	public function test_complex_tool_execution(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create test data.
		$category_id = $this->create_test_term( 'Test Category', 'category' );
		$tag_id      = $this->create_test_term( 'test-tag', 'post_tag' );

		$adapter = $this->get_mcp_adapter();

		// Test complex post creation with multiple features.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Complex Test Post',
					'content'   => '<!-- wp:paragraph --><p>This is a test paragraph.</p><!-- /wp:paragraph -->',
					'status'    => 'draft',
					'meta'      => array(
						'custom_field' => 'custom_value',
					),
					'tax_input' => array(
						'category' => array( $category_id ),
						'post_tag' => array( 'test-tag' ),
					),
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertArrayHasKey( 'id', $content );

		$post_id = $content['id'];

		// Verify post was created correctly.
		$post = get_post( $post_id );
		$this->assertNotNull( $post );
		$this->assertEquals( 'Complex Test Post', $post->post_title );
		$this->assertStringContains( 'wp:paragraph', $post->post_content );

		// Verify meta was set.
		$this->assertEquals( 'custom_value', get_post_meta( $post_id, 'custom_field', true ) );

		// Verify terms were assigned.
		$categories = wp_get_post_terms( $post_id, 'category' );
		$this->assertNotEmpty( $categories );
		$this->assertEquals( $category_id, $categories[0]->term_id );

		$tags = wp_get_post_terms( $post_id, 'post_tag' );
		$this->assertNotEmpty( $tags );
		$this->assertEquals( 'test-tag', $tags[0]->slug );
	}

	/**
	 * Test error handling in complex scenarios.
	 */
	public function test_error_handling_complex_scenarios(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with multiple validation errors.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'invalid_type',  // Invalid post type.
					'title'     => '',              // Empty title.
					'status'    => 'invalid_status', // Invalid status.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test concurrent tool execution.
	 */
	public function test_concurrent_tool_execution(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Execute multiple tools in sequence (simulating concurrent usage).
		$responses = array();

		$responses[] = $this->make_mcp_request( 'tools/list' );
		$responses[] = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);
		$responses[] = $this->make_mcp_request( 'initialize' );

		// All requests should succeed.
		foreach ( $responses as $index => $response ) {
			$this->assertEquals( 200, $response->get_status(), "Request {$index} should succeed" );
		}
	}

	/**
	 * Test tool execution with large payloads.
	 */
	public function test_tool_execution_with_large_payloads(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Create a large content string.
		$large_content = str_repeat( 'This is a test paragraph. ', 1000 );

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Large Content Post',
					'content'   => $large_content,
					'status'    => 'draft',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'content', $data );

		$content = $data['content'];
		$this->assertArrayHasKey( 'id', $content );

		// Verify the post was created with large content.
		$post = get_post( $content['id'] );
		$this->assertNotNull( $post );
		$this->assertEquals( strlen( $large_content ), strlen( $post->post_content ) );
	}

	/**
	 * Test REST API route registration.
	 */
	public function test_rest_api_route_registration(): void {
		$adapter = $this->get_mcp_adapter();

		$server = rest_get_server();
		$routes = $server->get_routes();

		// Verify our MCP endpoint is registered.
		$expected_route = '/mcp-adapter-example/mcp';
		$this->assertArrayHasKey( $expected_route, $routes );

		$route_config = $routes[ $expected_route ];
		$this->assertIsArray( $route_config );
		$this->assertNotEmpty( $route_config );

		// Verify route supports POST method.
		$first_handler = $route_config[0];
		$this->assertArrayHasKey( 'methods', $first_handler );
		$this->assertArrayHasKey( 'callback', $first_handler );
		$this->assertArrayHasKey( 'permission_callback', $first_handler );
	}

	/**
	 * Test authentication and authorization.
	 */
	public function test_authentication_and_authorization(): void {
		$adapter = $this->get_mcp_adapter();

		// Test without authentication.
		wp_set_current_user( 0 );

		$response = $this->make_mcp_request( 'tools/list' );

		// Should handle unauthenticated requests appropriately.
		// The exact behavior depends on the transport's permission callback.
		$this->assertContains( $response->get_status(), array( 200, 401, 403 ) );
	}

	/**
	 * Test MCP protocol version handling.
	 */
	public function test_protocol_version_handling(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request(
			'initialize',
			array(
				'protocolVersion' => '2024-11-05',
				'capabilities'    => array(),
				'clientInfo'      => array(
					'name'    => 'test-client',
					'version' => '1.0.0',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'protocolVersion', $data );
		$this->assertIsString( $data['protocolVersion'] );
	}

	/**
	 * Test server capabilities reporting.
	 */
	public function test_server_capabilities_reporting(): void {
		$adapter = $this->get_mcp_adapter();

		$response = $this->make_mcp_request( 'initialize' );

		$this->assertEquals( 200, $response->get_status() );

		$data         = $response->get_data();
		$capabilities = $data['capabilities'];

		$this->assertArrayHasKey( 'tools', $capabilities );

		$tools_capability = $capabilities['tools'];
		$this->assertIsArray( $tools_capability );
	}

	/**
	 * Test tool schema validation.
	 */
	public function test_tool_schema_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with invalid schema (missing required field).
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					// Missing required 'post_type' field.
					'title'   => 'Test Post',
					'content' => 'Test content',
				),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertStringContainsString( 'invalid', strtolower( $data['message'] ?? '' ) );
	}
}
