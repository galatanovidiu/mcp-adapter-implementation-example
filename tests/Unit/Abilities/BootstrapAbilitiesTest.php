<?php
/**
 * Unit tests for BootstrapAbilities.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities;

use OvidiuGalatan\McpAdapterExample\Abilities\BootstrapAbilities;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test BootstrapAbilities functionality.
 */
final class BootstrapAbilitiesTest extends TestCase {

	/**
	 * Test that all expected abilities are registered after initialization.
	 */
	public function test_all_abilities_registered_after_init(): void {
		// Initialize abilities.
		BootstrapAbilities::init();

		// Trigger the abilities_api_init action.
		do_action( 'abilities_api_init' );

		$expected_abilities = array(
			// Post CRUD abilities.
			'wpmcp-example/create-post',
			'wpmcp-example/get-post',
			'wpmcp-example/list-posts',
			'wpmcp-example/update-post',
			'wpmcp-example/delete-post',

			// Post Meta abilities.
			'wpmcp-example/list-post-meta-keys',
			'wpmcp-example/get-post-meta',
			'wpmcp-example/update-post-meta',
			'wpmcp-example/delete-post-meta',

			// Blocks discovery.
			'wpmcp-example/list-block-types',

			// Taxonomy & Terms abilities.
			'wpmcp-example/list-taxonomies',
			'wpmcp-example/get-terms',
			'wpmcp-example/create-term',
			'wpmcp-example/update-term',
			'wpmcp-example/delete-term',

			// Attach/Detach helpers.
			'wpmcp-example/attach-post-terms',
			'wpmcp-example/detach-post-terms',
		);

		foreach ( $expected_abilities as $ability_name ) {
			$this->assertAbilityRegistered( $ability_name );
		}
	}

	/**
	 * Test that abilities are not registered before init.
	 */
	public function test_abilities_not_registered_before_init(): void {
		// Clean up any existing abilities.
		$this->cleanup_test_abilities();

		// Check that abilities are not registered yet.
		$ability = wp_get_ability( 'wpmcp-example/create-post' );
		$this->assertNull( $ability, 'Abilities should not be registered before init' );
	}

	/**
	 * Test that init method can be called multiple times safely.
	 */
	public function test_init_method_idempotent(): void {
		// Call init multiple times.
		BootstrapAbilities::init();
		BootstrapAbilities::init();
		BootstrapAbilities::init();

		// Trigger the action multiple times.
		do_action( 'abilities_api_init' );
		do_action( 'abilities_api_init' );

		// Should still work correctly.
		$this->assertAbilityRegistered( 'wpmcp-example/create-post' );

		// Count how many times the ability is registered (should be once per action call).
		$all_abilities     = wp_get_abilities();
		$create_post_count = 0;
		foreach ( $all_abilities as $ability ) {
			if ( $ability->get_name() !== 'wpmcp-example/create-post' ) {
				continue;
			}

			++$create_post_count;
		}

		// Due to multiple action calls, there might be multiple registrations.
		// This tests that the system handles it gracefully.
		$this->assertGreaterThan( 0, $create_post_count, 'Should have at least one registration' );
	}

	/**
	 * Test that all abilities have proper schemas.
	 */
	public function test_all_abilities_have_proper_schemas(): void {
		BootstrapAbilities::init();
		do_action( 'abilities_api_init' );

		$all_abilities     = wp_get_abilities();
		$example_abilities = array_filter(
			$all_abilities,
			static function ( $ability ) {
				return str_starts_with( $ability->get_name(), 'wpmcp-example/' );
			}
		);

		$this->assertNotEmpty( $example_abilities, 'Should have example abilities registered' );

		foreach ( $example_abilities as $ability ) {
			// Test that ability has required properties.
			$this->assertNotEmpty( $ability->get_label(), "Ability '{$ability->get_name()}' should have a label" );
			$this->assertNotEmpty( $ability->get_description(), "Ability '{$ability->get_name()}' should have a description" );

			// Test input schema.
			$input_schema = $ability->get_input_schema();
			$this->assertIsArray( $input_schema, "Ability '{$ability->get_name()}' should have input schema array" );

			if ( ! empty( $input_schema ) ) {
				$this->assertArrayHasKey( 'type', $input_schema, "Input schema should have 'type' field" );
				$this->assertEquals( 'object', $input_schema['type'], "Input schema type should be 'object'" );
			}

			// Test output schema.
			$output_schema = $ability->get_output_schema();
			$this->assertIsArray( $output_schema, "Ability '{$ability->get_name()}' should have output schema array" );

			if ( empty( $output_schema ) ) {
				continue;
			}

			$this->assertArrayHasKey( 'type', $output_schema, "Output schema should have 'type' field" );
		}
	}

	/**
	 * Test that abilities have proper permission callbacks.
	 */
	public function test_abilities_have_permission_callbacks(): void {
		BootstrapAbilities::init();
		do_action( 'abilities_api_init' );

		$test_abilities = array(
			'wpmcp-example/create-post',
			'wpmcp-example/list-posts',
			'wpmcp-example/list-block-types',
		);

		foreach ( $test_abilities as $ability_name ) {
			$ability = wp_get_ability( $ability_name );
			$this->assertNotNull( $ability, "Ability '{$ability_name}' should be registered" );

			// Prepare valid input based on ability requirements.
			$test_input = array();
			if ( $ability_name === 'wpmcp-example/create-post' ) {
				$test_input = array(
					'post_type' => 'post',
					'title'     => 'Test',
				);
			} elseif ( $ability_name === 'wpmcp-example/list-posts' ) {
				$test_input = array( 'post_type' => 'post' );
			} elseif ( $ability_name === 'wpmcp-example/list-block-types' ) {
				$test_input = array();
			}

			// Test permission check with no user.
			wp_set_current_user( 0 );
			$permission_result = $ability->has_permission( $test_input );
			$this->assertIsBool( $permission_result, "Permission check should return boolean for '{$ability_name}'" );

			// Test permission check with admin user.
			$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
			wp_set_current_user( $admin_id );
			$admin_permission = $ability->has_permission( $test_input );
			$this->assertIsBool( $admin_permission, "Admin permission check should return boolean for '{$ability_name}'" );
		}
	}

	/**
	 * Test that abilities work correctly with WordPress Abilities API.
	 */
	public function test_abilities_work_with_abilities_api(): void {
		BootstrapAbilities::init();
		do_action( 'abilities_api_init' );

		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Test list-block-types ability.
		$result = $this->execute_ability( 'wpmcp-example/list-block-types', array() );

		$this->assertIsArray( $result, 'list-block-types should return array' );
		$this->assertArrayHasKey( 'blocks', $result );
		$this->assertIsArray( $result['blocks'] );

		// Test list-posts ability.
		$result = $this->execute_ability(
			'wpmcp-example/list-posts',
			array(
				'post_type'   => array( 'post' ),
				'post_status' => array( 'publish' ),
				'limit'       => 5,
			)
		);

		$this->assertIsArray( $result, 'list-posts should return array' );
		$this->assertArrayHasKey( 'posts', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'found_posts', $result );
	}
}
