<?php
/**
 * Unit tests for UpdateTerm ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\UpdateTerm;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test UpdateTerm ability functionality.
 */
final class UpdateTermTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/update-term' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = UpdateTerm::check_permission( $input );

		$this->assertTrue( $has_permission, 'Editor should have permission to update terms' );
	}

	/**
	 * Test permission checking with invalid taxonomy.
	 */
	public function test_permission_check_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'invalid_taxonomy' );
		$has_permission = UpdateTerm::check_permission( $input );

		$this->assertFalse( $has_permission, 'Should not grant permission for invalid taxonomy' );
	}

	/**
	 * Test update with missing taxonomy.
	 */
	public function test_update_with_missing_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'term_id' => 1 );
		$result = UpdateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test update with invalid taxonomy.
	 */
	public function test_update_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'invalid_taxonomy',
			'term_id'  => 1,
		);
		$result = UpdateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test update with missing term ID.
	 */
	public function test_update_with_missing_term_id(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'taxonomy' => 'category' );
		$result = UpdateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_term_id', $result['error']['code'] );
	}

	/**
	 * Test update with term not found.
	 */
	public function test_update_with_term_not_found(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'category',
			'term_id'  => 999999,
		);
		$result = UpdateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'term_not_found', $result['error']['code'] );
	}

	/**
	 * Test update with invalid parent.
	 */
	public function test_update_with_invalid_parent(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$term_id = $this->create_test_term( 'Child Category', 'category' );

		$input  = array(
			'taxonomy' => 'category',
			'term_id'  => $term_id,
			'parent'   => 999999,
		);
		$result = UpdateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_parent', $result['error']['code'] );
	}

	/**
	 * Test successful term update.
	 */
	public function test_successful_term_update(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$parent_id = $this->create_test_term( 'Parent Category', 'category' );
		$term_id   = $this->create_test_term( 'Original Category', 'category' );

		$input = array(
			'taxonomy'    => 'category',
			'term_id'     => $term_id,
			'name'        => 'Updated Category',
			'slug'        => 'updated-category',
			'description' => 'Updated description',
			'parent'      => $parent_id,
		);

		$result = UpdateTerm::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$term = get_term( $result['id'], 'category' );
		$this->assertNotNull( $term );
		$this->assertSame( 'Updated Category', $term->name );
		$this->assertSame( 'updated-category', $term->slug );
		$this->assertSame( 'Updated description', $term->description );
		$this->assertSame( $parent_id, (int) $term->parent );
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$term_id = $this->create_test_term( 'API Category', 'category' );

		$input = array(
			'taxonomy' => 'category',
			'term_id'  => $term_id,
			'name'     => 'API Updated Category',
		);

		$result = $this->execute_ability( 'wpmcp-example/update-term', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertSame( 'API Updated Category', get_term( $result['id'], 'category' )->name );
	}
}
