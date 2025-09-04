<?php
/**
 * Tests for schema validation and compatibility between abilities-api and mcp-adapter.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Integration
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Integration;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test schema validation and compatibility.
 */
final class SchemaValidationTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		BootstrapAbilities::init();
	}

	/**
	 * Test that all ability schemas are valid JSON Schema.
	 */
	public function test_all_ability_schemas_are_valid(): void {
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
			$input_schema  = $ability->get_input_schema();
			$output_schema = $ability->get_output_schema();

			// Test input schema validity.
			if ( ! empty( $input_schema ) ) {
				$this->assertIsArray( $input_schema, "Input schema for '{$ability->get_name()}' should be array" );
				$this->assertArrayHasKey( 'type', $input_schema, "Input schema should have 'type'" );

				if ( $input_schema['type'] === 'object' ) {
					$this->assertArrayHasKey( 'properties', $input_schema, "Object schemas should have 'properties'" );
					$this->assertIsArray( $input_schema['properties'], "'properties' should be array" );
				}
			}

			// Test output schema validity.
			if ( empty( $output_schema ) ) {
				continue;
			}

			$this->assertIsArray( $output_schema, "Output schema for '{$ability->get_name()}' should be array" );
			$this->assertArrayHasKey( 'type', $output_schema, "Output schema should have 'type'" );
		}
	}

	/**
	 * Test that MCP tools have compatible schemas.
	 */
	public function test_mcp_tools_have_compatible_schemas(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		$this->assertNotEmpty( $tools, 'Should have tools registered' );

		foreach ( $tools as $tool ) {
			$tool_array = $tool->to_array();

			// Verify required MCP tool fields.
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

	/**
	 * Test input validation with various data types.
	 */
	public function test_input_validation_with_various_data_types(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test string validation.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 123, // Should be string, not integer.
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid string type should fail validation' );

		// Test array validation.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => 'post', // Should be array, not string.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid array type should fail validation' );

		// Test integer validation.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'limit'     => 'ten', // Should be integer, not string.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid integer type should fail validation' );
	}

	/**
	 * Test enum validation.
	 */
	public function test_enum_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test invalid enum value for orderby.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'orderby'   => 'invalid_orderby', // Not in enum.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid enum value should fail validation' );

		// Test valid enum value.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'orderby'   => 'title', // Valid enum value.
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Valid enum value should pass validation' );
	}

	/**
	 * Test required field validation.
	 */
	public function test_required_field_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test missing required field.
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

		$this->assertEquals( 500, $response->get_status(), 'Missing required field should fail validation' );

		// Test with required field present.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'post', // Required field present.
					'title'     => 'Test Post',
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Required field present should pass validation' );
	}

	/**
	 * Test nested object validation.
	 */
	public function test_nested_object_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test valid nested object (date_query).
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'  => array( 'post' ),
					'date_query' => array(
						'year'  => 2023,
						'month' => 12,
					),
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Valid nested object should pass validation' );

		// Test invalid nested object structure.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'  => array( 'post' ),
					'date_query' => array(
						'year'  => 'invalid_year', // Should be integer.
						'month' => 25, // Invalid month (should be 1-12).
					),
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid nested object should fail validation' );
	}

	/**
	 * Test array item validation.
	 */
	public function test_array_item_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test valid array items.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post', 'page' ), // Valid array of strings.
					'post_status' => array( 'publish', 'draft' ), // Valid array of strings.
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Valid array items should pass validation' );

		// Test invalid array items.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post', 123 ), // Mixed types in array.
					'post_status' => array( 'publish' ),
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid array items should fail validation' );
	}

	/**
	 * Test default value handling.
	 */
	public function test_default_value_handling(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test that defaults are applied when values are not provided.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					// Don't specify limit, orderby, order - should use defaults.
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Should succeed with default values' );

		$data    = $response->get_data();
		$content = $data['content'];

		// Verify defaults were applied (limit should be 10, order should be DESC).
		$this->assertLessThanOrEqual( 10, $content['total'], 'Should respect default limit' );
	}

	/**
	 * Test output schema validation.
	 */
	public function test_output_schema_validation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Create a test post to ensure we have data.
		$this->create_test_post(
			array(
				'post_title'  => 'Schema Test Post',
				'post_status' => 'publish',
			)
		);

		$adapter = $this->get_mcp_adapter();

		// Test list-posts output schema.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'   => array( 'post' ),
					'post_status' => array( 'publish' ),
					'limit'       => 1,
				),
			)
		);

		$this->assertEquals( 200, $response->get_status() );

		$data    = $response->get_data();
		$content = $data['content'];

		// Verify output matches expected schema.
		$this->assertArrayHasKey( 'posts', $content );
		$this->assertArrayHasKey( 'total', $content );
		$this->assertArrayHasKey( 'found_posts', $content );
		$this->assertArrayHasKey( 'max_pages', $content );

		$this->assertIsArray( $content['posts'] );
		$this->assertIsInt( $content['total'] );
		$this->assertIsInt( $content['found_posts'] );
		$this->assertIsInt( $content['max_pages'] );

		// Verify post structure matches schema.
		if ( empty( $content['posts'] ) ) {
			return;
		}

		$post            = $content['posts'][0];
		$required_fields = array( 'id', 'post_type', 'status', 'title', 'link', 'date' );

		foreach ( $required_fields as $field ) {
			$this->assertArrayHasKey( $field, $post, "Post should have required field '{$field}'" );
		}

		$this->assertIsInt( $post['id'] );
		$this->assertIsString( $post['post_type'] );
		$this->assertIsString( $post['status'] );
		$this->assertIsString( $post['title'] );
		$this->assertIsString( $post['link'] );
	}

	/**
	 * Test that MCP adapter correctly transforms ability schemas.
	 */
	public function test_mcp_adapter_transforms_schemas_correctly(): void {
		$adapter = $this->get_mcp_adapter();

		$server = $this->get_mcp_server();
		$tools  = $server->get_tools();

		foreach ( $tools as $tool ) {
			$tool_array   = $tool->to_array();
			$ability_name = str_replace( '--', '/', $tool_array['name'] );

			// Get the original ability.
			$ability = wp_get_ability( $ability_name );
			if ( ! $ability ) {
				continue; // Skip if ability not found.
			}

			$ability_input_schema = $ability->get_input_schema();
			$tool_input_schema    = $tool_array['inputSchema'];

			// Verify schemas are compatible.
			if ( empty( $ability_input_schema ) || empty( $tool_input_schema ) ) {
				continue;
			}

			$this->assertEquals(
				$ability_input_schema['type'],
				$tool_input_schema['type'],
				"Schema types should match for tool '{$tool_array['name']}'"
			);

			if ( ! isset( $ability_input_schema['properties'] ) ) {
				continue;
			}

			$this->assertArrayHasKey(
				'properties',
				$tool_input_schema,
				'Tool should have properties if ability has properties'
			);
		}
	}

	/**
	 * Test complex schema validation scenarios.
	 */
	public function test_complex_schema_validation_scenarios(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test complex meta_query validation.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'  => array( 'post' ),
					'meta_query' => array(
						array(
							'key'     => 'test_meta',
							'value'   => 'test_value',
							'compare' => '=',
						),
						array(
							'key'     => 'another_meta',
							'value'   => 'another_value',
							'compare' => 'LIKE',
						),
					),
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Complex meta_query should pass validation' );

		// Test invalid meta_query structure.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type'  => array( 'post' ),
					'meta_query' => array(
						array(
							// Missing required 'key' field.
							'value'   => 'test_value',
							'compare' => '=',
						),
					),
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Invalid meta_query structure should fail validation' );
	}

	/**
	 * Test schema validation with boundary values.
	 */
	public function test_schema_validation_with_boundary_values(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test minimum values.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'limit'     => 1, // Minimum allowed.
					'offset'    => 0, // Minimum allowed.
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Minimum values should pass validation' );

		// Test maximum values.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'limit'     => 100, // Maximum allowed.
				),
			)
		);

		$this->assertEquals( 200, $response->get_status(), 'Maximum values should pass validation' );

		// Test exceeding maximum.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'limit'     => 101, // Exceeds maximum.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Values exceeding maximum should fail validation' );

		// Test below minimum.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-list-posts',
				'arguments' => array(
					'post_type' => array( 'post' ),
					'limit'     => 0, // Below minimum.
				),
			)
		);

		$this->assertEquals( 500, $response->get_status(), 'Values below minimum should fail validation' );
	}

	/**
	 * Test that schema validation errors provide helpful messages.
	 */
	public function test_schema_validation_error_messages(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$adapter = $this->get_mcp_adapter();

		// Test with invalid data to get error message.
		$response = $this->make_mcp_request(
			'tools/call',
			array(
				'name'      => 'wpmcp-example-create-post',
				'arguments' => array(
					'post_type' => 'invalid_type',
				),
			)
		);

		$this->assertEquals( 500, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'message', $data );
		$this->assertIsString( $data['message'] );
		$this->assertNotEmpty( $data['message'], 'Error message should not be empty' );
	}
}
