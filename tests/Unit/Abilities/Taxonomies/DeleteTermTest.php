<?php
/**
 * Unit tests for DeleteTerm ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\DeleteTerm;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test DeleteTerm ability functionality.
 */
final class DeleteTermTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/delete-term' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = DeleteTerm::check_permission( $input );

		$this->assertTrue( $has_permission, 'Editor should have permission to delete terms' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = DeleteTerm::check_permission( $input );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to delete terms' );
	}

	/**
	 * Test delete with missing taxonomy.
	 */
	public function test_delete_with_missing_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'term_id' => 1 );
		$result = DeleteTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test delete with invalid taxonomy.
	 */
	public function test_delete_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'invalid_taxonomy',
			'term_id'  => 1,
		);
		$result = DeleteTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test delete with missing term ID.
	 */
	public function test_delete_with_missing_term_id(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'taxonomy' => 'category' );
		$result = DeleteTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_term_id', $result['error']['code'] );
	}

	/**
	 * Test delete with term not found.
	 */
	public function test_delete_with_term_not_found(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'category',
			'term_id'  => 999999,
		);
		$result = DeleteTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'term_not_found', $result['error']['code'] );
	}

	/**
	 * Test successful term deletion.
	 */
	public function test_successful_term_deletion(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$term_id = $this->create_test_term( 'Delete Category', 'category' );

		$input  = array(
			'taxonomy' => 'category',
			'term_id'  => $term_id,
		);
		$result = DeleteTerm::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertNull( get_term( $term_id, 'category' ) );
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$term_id = $this->create_test_term( 'API Delete Category', 'category' );

		$input = array(
			'taxonomy' => 'category',
			'term_id'  => $term_id,
		);

		$result = $this->execute_ability( 'wpmcp-example/delete-term', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'deleted', $result );
		$this->assertTrue( $result['deleted'] );
		$this->assertNull( get_term( $term_id, 'category' ) );
	}
}
