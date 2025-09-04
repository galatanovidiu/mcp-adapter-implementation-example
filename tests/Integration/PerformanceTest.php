<?php
/**
 * Performance tests for MCP Adapter integration.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test performance characteristics of the MCP Adapter integration.
 */
final class PerformanceTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test initialization performance.
	 */
	public function test_initialization_performance(): void {
		$start_time = microtime( true );

		// Initialize adapter.
		$adapter = $this->get_mcp_adapter();

		$initialization_time = microtime( true ) - $start_time;

		// Initialization should be fast (under 100ms in most cases).
		$this->assertLessThan( 0.5, $initialization_time, 'MCP adapter initialization should be fast' );

		// Verify server was created.
		$server = $this->get_mcp_server();
		$this->assertNotNull( $server, 'Server should be created during initialization' );
	}

	/**
	 * Test tools listing performance.
	 */
	public function test_tools_listing_performance(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$start_time = microtime( true );

		$response = $this->make_mcp_request( 'tools/list' );

		$execution_time = microtime( true ) - $start_time;

		$this->assertEquals( 200, $response->get_status() );

		// Tools listing should be fast (under 200ms).
		$this->assertLessThan( 0.2, $execution_time, 'Tools listing should be fast' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'tools', $data );
		$this->assertNotEmpty( $data['tools'] );
	}

	/**
	 * Test tool execution performance.
	 */
	public function test_tool_execution_performance(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$start_time = microtime( true );

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);

		$execution_time = microtime( true ) - $start_time;

		$this->assertEquals( 200, $response->get_status() );

		// Tool execution should be reasonably fast (under 500ms).
		$this->assertLessThan( 0.5, $execution_time, 'Tool execution should be reasonably fast' );
	}

	/**
	 * Test performance with multiple concurrent requests.
	 */
	public function test_concurrent_requests_performance(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$start_time = microtime( true );

		// Simulate multiple concurrent requests.
		$responses = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$responses[] = $this->make_mcp_request(
				'tools/call',
				array(
					'name'      => 'wpmcp-example-list-block-types',
					'arguments' => array(),
				)
			);
		}

		$total_time = microtime( true ) - $start_time;

		// All requests should succeed.
		foreach ( $responses as $index => $response ) {
			$this->assertEquals( 200, $response->get_status(), "Request {$index} should succeed" );
		}

		// Total time for 10 requests should be reasonable (under 2 seconds).
		$this->assertLessThan( 2.0, $total_time, 'Multiple concurrent requests should complete in reasonable time' );

		// Average time per request should be acceptable.
		$average_time = $total_time / count( $responses );
		$this->assertLessThan( 0.3, $average_time, 'Average request time should be acceptable' );
	}

	/**
	 * Test performance with large result sets.
	 */
	public function test_large_result_set_performance(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create multiple posts for testing.
		for ( $i = 1; $i <= 50; $i++ ) {
			$this->create_test_post(
				array(
					'post_title'  => "Performance Test Post {$i}",
					'post_status' => 'publish',
				)
			);
		}

		$adapter = $this->get_mcp_adapter();

		$start_time = microtime( true );

		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'          => array( 'post' ),
					'post_status'        => array( 'publish' ),
					'limit'              => 50,
					'include_meta'       => true,
					'include_taxonomies' => true,
				),
			)
		);

		$execution_time = microtime( true ) - $start_time;

		$this->assertEquals( 200, $response->get_status() );

		// Large result set should still be processed in reasonable time (under 1 second).
		$this->assertLessThan( 1.0, $execution_time, 'Large result set should be processed efficiently' );

		$data    = $response->get_data();
		$content = $data['content'];
		$this->assertGreaterThanOrEqual( 50, $content['found_posts'] );
	}

	/**
	 * Test memory usage during operations.
	 */
	public function test_memory_usage_during_operations(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		$initial_memory = memory_get_usage();

		// Perform multiple operations.
		for ( $i = 1; $i <= 10; $i++ ) {
			$this->make_mcp_request(
				'tools/call',
				array(
					'name'      => 'wpmcp-example-list-block-types',
					'arguments' => array(),
				)
			);
		}

		$final_memory    = memory_get_usage();
		$memory_increase = $final_memory - $initial_memory;

		// Memory increase should be reasonable (under 10MB for 10 requests).
		$max_memory_increase = 10 * 1024 * 1024; // 10MB.
		$this->assertLessThan(
			$max_memory_increase,
			$memory_increase,
			'Memory usage should not increase excessively during operations'
		);
	}

	/**
	 * Test performance with complex queries.
	 */
	public function test_complex_query_performance(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create test data.
		$category_id = $this->create_test_term( 'Performance Category', 'category' );
		$tag_id      = $this->create_test_term( 'performance-tag', 'post_tag' );

		for ( $i = 1; $i <= 20; $i++ ) {
			$post_id = $this->create_test_post(
				array(
					'post_title'  => "Complex Query Test Post {$i}",
					'post_status' => 'publish',
					'meta_input'  => array(
						'priority'    => $i % 3 === 0 ? 'high' : 'normal',
						'test_number' => $i,
					),
				)
			);

			if ( $i % 2 === 0 ) {
				wp_set_post_terms( $post_id, array( $category_id ), 'category' );
			}
			if ( $i % 3 !== 0 ) {
				continue;
			}

			wp_set_post_terms( $post_id, array( $tag_id ), 'post_tag' );
		}

		$adapter = $this->get_mcp_adapter();

		$start_time = microtime( true );

		// Execute complex query with multiple filters.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'          => array( 'post' ),
					'post_status'        => array( 'publish' ),
					'meta_query'         => array(
						array(
							'key'   => 'priority',
							'value' => 'high',
						),
					),
					'tax_query'          => array(
						array(
							'taxonomy' => 'category',
							'field'    => 'term_id',
							'terms'    => array( $category_id ),
						),
					),
					'include_meta'       => true,
					'include_taxonomies' => true,
				),
			)
		);

		$execution_time = microtime( true ) - $start_time;

		$this->assertEquals( 200, $response->get_status() );

		// Complex query should still execute in reasonable time (under 800ms).
		$this->assertLessThan( 0.8, $execution_time, 'Complex queries should execute efficiently' );

		$data    = $response->get_data();
		$content = $data['content'];
		$this->assertArrayHasKey( 'posts', $content );
	}

	/**
	 * Test REST API overhead.
	 */
	public function test_rest_api_overhead(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test direct ability execution.
		$start_time    = microtime( true );
		$direct_result = $this->execute_ability( 'wpmcp-example/list-block-types', array() );
		$direct_time   = microtime( true ) - $start_time;

		$this->assertIsArray( $direct_result );

		// Test MCP REST API execution.
		$start_time = microtime( true );
		$response   = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-block-types',
				'arguments' => array(),
			)
		);
		$rest_time  = microtime( true ) - $start_time;

		$this->assertEquals( 200, $response->get_status() );

		// REST API overhead should be reasonable (less than 3x direct execution).
		$overhead_ratio = $rest_time / $direct_time;
		$this->assertLessThan( 3.0, $overhead_ratio, 'REST API overhead should be reasonable' );

		// Both should return equivalent data.
		$rest_data = $response->get_data()['content'];
		$this->assertEquals(
			count( $direct_result['blocks'] ),
			count( $rest_data['blocks'] ),
			'Direct and REST API execution should return same number of blocks'
		);
	}
}
