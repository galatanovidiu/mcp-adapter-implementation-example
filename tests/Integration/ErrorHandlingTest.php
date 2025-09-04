<?php
/**
 * Tests for error handling between abilities-api and mcp-adapter.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test error handling across the integration.
 */
final class ErrorHandlingTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test WordPress ability validation errors are properly propagated to MCP.
	 */
	public function test_ability_validation_errors_propagated(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with invalid input that fails WordPress ability validation.
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
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'invalid', strtolower( $data['message'] ) );
	}

	/**
	 * Test WordPress ability execution errors are properly handled.
	 */
	public function test_ability_execution_errors_handled(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with data that causes execution error.
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

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test permission errors are properly handled.
	 */
	public function test_permission_errors_handled(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Try to execute a tool without sufficient permissions.
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
		$this->assertArrayHasKey( 'message', $data );
		$this->assertStringContainsString( 'permission', strtolower( $data['message'] ?? '' ) );
	}

	/**
	 * Test malformed JSON-RPC requests.
	 */
	public function test_malformed_jsonrpc_requests(): void {
		$adapter = $this->get_mcp_adapter();

		// Test request without method.
		$request = new \WP_REST_Request( 'POST', '/wp-json/mcp-adapter-example/mcp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
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
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test invalid JSON in request body.
	 */
	public function test_invalid_json_in_request_body(): void {
		$adapter = $this->get_mcp_adapter();

		$request = new \WP_REST_Request( 'POST', '/wp-json/mcp-adapter-example/mcp' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '{"invalid": json}' ); // Invalid JSON.

		$server   = rest_get_server();
		$response = $server->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * Test error handling with database failures.
	 */
	public function test_database_failure_handling(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with data that might cause database issues.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => str_repeat( 'Very long title ', 1000 ), // Extremely long title.
					'author'    => 999999, // Non-existent user ID.
				),
			)
		);

		// Should handle gracefully (either succeed with sanitized data or fail gracefully).
		$this->assertContains( $response->get_status(), array( 200, 500 ) );

		if ( $response->get_status() !== 500 ) {
			return;
		}

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test error consistency across different tools.
	 */
	public function test_error_consistency_across_tools(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$tools_to_test = array(
			'wpmcp-example-create-post',
			'wpmcp-example-update-post',
			'wpmcp-example-get-post',
		);

		foreach ( $tools_to_test as $tool_name ) {
			// Test with missing required parameters.
			$response = $this->make_mcp_request(
				'tools/call',
				array(
					'name'      => $tool_name,
					'arguments' => array(), // Empty arguments should cause validation errors.
				)
			);

			$this->assertEquals( 500, $response->get_status(), "Tool '{$tool_name}' should return error for empty arguments" );

			$data = $response->get_data();
			$this->assertArrayHasKey( 'code', $data, "Error response should have 'code' field" );
			$this->assertArrayHasKey( 'message', $data, "Error response should have 'message' field" );
			$this->assertIsString( $data['message'], 'Error message should be string' );
			$this->assertNotEmpty( $data['message'], 'Error message should not be empty' );
		}
	}

	/**
	 * Test error handling with edge cases.
	 */
	public function test_error_handling_edge_cases(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with null values.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => null,
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		// Test with extremely large data.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Test Post',
					'content'   => str_repeat( 'Large content block. ', 10000 ),
				),
			)
		);

		// Should either succeed or fail gracefully.
		$this->assertContains( $response->get_status(), array( 200, 500 ) );

		// Test with special characters.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Test Post with Special Characters: áéíóú 中文 🚀',
					'content'   => 'Content with special characters: <script>alert("test")</script>',
				),
			)
		);

		// Should handle special characters gracefully.
		$this->assertContains( $response->get_status(), array( 200, 500 ) );

		if ( $response->get_status() !== 200 ) {
			return;
		}

		$data    = $response->get_data();
		$post_id = $data['content']['id'];
		$post    = get_post( $post_id );

		// Verify content was properly sanitized.
		$this->assertNotNull( $post );
		$this->assertStringNotContains( '<script>', $post->post_content );
	}

	/**
	 * Test error recovery scenarios.
	 */
	public function test_error_recovery_scenarios(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// First, cause an error.
		$error_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'invalid_type',
				),
			)
		);

		$this->assertEquals( 500, $error_response->get_status() );

		// Then, make a successful request to ensure system recovered.
		$success_response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 200, $success_response->get_status(), 'System should recover after error' );

		// Make another successful request.
		$success_response2 = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post',
					'title'     => 'Recovery Test Post',
				),
			)
		);

		$this->assertEquals( 200, $success_response2->get_status(), 'Should be able to make successful requests after errors' );
	}

	/**
	 * Test that error responses follow JSON-RPC 2.0 format.
	 */
	public function test_error_responses_follow_jsonrpc_format(): void {
		$adapter = $this->get_mcp_adapter();

		// Make a request that will cause an error.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'non-existent-tool',
				'arguments' => array(),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();

		// Verify JSON-RPC error format.
		$this->assertArrayHasKey( 'code', $data );
		$this->assertArrayHasKey( 'message', $data );
		$this->assertIsInt( $data['code'] );
		$this->assertIsString( $data['message'] );

		// Verify error codes are in valid ranges.
		$error_code = $data['code'];
		$this->assertTrue(
			( $error_code >= -32768 && $error_code <= -32000 ) || $error_code > 0,
			'Error code should be in valid JSON-RPC range'
		);
	}
}
