<?php
/**
 * Unit tests for ListTaxonomies ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\ListTaxonomies;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListTaxonomies ability functionality.
 */
final class ListTaxonomiesTest extends TestCase {

	/**
	 * Set up before class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		// Ability registration is handled by the base TestCase via BootstrapAbilities::init()
	}

	/**
	 * Test ability registration.
	 */
	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'core/list-taxonomies' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$has_permission = ListTaxonomies::check_permission( array() );

		$this->assertTrue( $has_permission, 'Author should have permission to list taxonomies' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$has_permission = ListTaxonomies::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to list taxonomies' );
	}

	/**
	 * Test listing taxonomies includes category.
	 */
	public function test_list_taxonomies_includes_category(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$result = ListTaxonomies::execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'taxonomies', $result );

		$names = array_map(
			static function ( $taxonomy ) {
				return $taxonomy['name'];
			},
			$result['taxonomies']
		);

		$this->assertContains( 'category', $names );
	}

	/**
	 * Test listing taxonomies filtered by post type.
	 */
	public function test_list_taxonomies_filtered_by_post_type(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$result = ListTaxonomies::execute(
			array(
				'post_type' => 'post',
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'taxonomies', $result );
		$this->assertNotEmpty( $result['taxonomies'] );

		foreach ( $result['taxonomies'] as $taxonomy ) {
			$this->assertContains( 'post', $taxonomy['object_types'] );
		}
	}

	/**
	 * Test listing taxonomies with invalid post type.
	 */
	public function test_list_taxonomies_with_invalid_post_type(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$result = ListTaxonomies::execute(
			array(
				'post_type' => 'not-a-real-type',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( array(), $result['taxonomies'] );
	}

	/**
	 * Test listing taxonomies with show_in_rest filter.
	 */
	public function test_list_taxonomies_with_show_in_rest_filter(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$taxonomy_name = 'hidden_taxonomy';
		register_taxonomy(
			$taxonomy_name,
			'post',
			array(
				'label'        => 'Hidden Taxonomy',
				'show_in_rest' => false,
			)
		);

		try {
			$result = ListTaxonomies::execute( array() );

			$names = array_map(
				static function ( $taxonomy ) {
					return $taxonomy['name'];
				},
				$result['taxonomies']
			);

			$this->assertNotContains( $taxonomy_name, $names );

			$result_with_hidden = ListTaxonomies::execute(
				array(
					'only_show_in_rest' => false,
				)
			);

			$names_with_hidden = array_map(
				static function ( $taxonomy ) {
					return $taxonomy['name'];
				},
				$result_with_hidden['taxonomies']
			);

			$this->assertContains( $taxonomy_name, $names_with_hidden );
		} finally {
			if ( function_exists( 'unregister_taxonomy' ) ) {
				unregister_taxonomy( $taxonomy_name );
			}
		}
	}

	/**
	 * Test listing taxonomies with include_private filter.
	 */
	public function test_list_taxonomies_with_include_private_filter(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$taxonomy_name = '_private_taxonomy';
		register_taxonomy(
			$taxonomy_name,
			'post',
			array(
				'label'        => 'Private Taxonomy',
				'show_in_rest' => true,
			)
		);

		try {
			$result = ListTaxonomies::execute( array() );

			$names = array_map(
				static function ( $taxonomy ) {
					return $taxonomy['name'];
				},
				$result['taxonomies']
			);

			$this->assertNotContains( $taxonomy_name, $names );

			$result_with_private = ListTaxonomies::execute(
				array(
					'include_private' => true,
				)
			);

			$names_with_private = array_map(
				static function ( $taxonomy ) {
					return $taxonomy['name'];
				},
				$result_with_private['taxonomies']
			);

			$this->assertContains( $taxonomy_name, $names_with_private );
		} finally {
			if ( function_exists( 'unregister_taxonomy' ) ) {
				unregister_taxonomy( $taxonomy_name );
			}
		}
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $user_id );

		$result = $this->execute_ability( 'core/list-taxonomies', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'taxonomies', $result );
		$this->assertNotEmpty( $result['taxonomies'] );
	}
}
