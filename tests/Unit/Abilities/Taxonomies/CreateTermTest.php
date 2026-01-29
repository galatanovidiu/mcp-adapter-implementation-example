<?php
/**
 * Unit tests for CreateTerm ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Taxonomies;

use OvidiuGalatan\McpAdapterExample\Abilities\Taxonomies\CreateTerm;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test CreateTerm ability functionality.
 */
final class CreateTermTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/create-term' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = CreateTerm::check_permission( $input );

		$this->assertTrue( $has_permission, 'Editor should have permission to create terms' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'category' );
		$has_permission = CreateTerm::check_permission( $input );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to create terms' );
	}

	/**
	 * Test permission checking with invalid taxonomy.
	 */
	public function test_permission_check_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$input          = array( 'taxonomy' => 'invalid_taxonomy' );
		$has_permission = CreateTerm::check_permission( $input );

		$this->assertFalse( $has_permission, 'Should not grant permission for invalid taxonomy' );
	}

	/**
	 * Test term creation with missing taxonomy.
	 */
	public function test_term_creation_with_missing_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'name' => 'Test Category' );
		$result = CreateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test term creation with invalid taxonomy.
	 */
	public function test_term_creation_with_invalid_taxonomy(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'invalid_taxonomy',
			'name'     => 'Test Category',
		);
		$result = CreateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_taxonomy', $result['error']['code'] );
	}

	/**
	 * Test term creation with missing name.
	 */
	public function test_term_creation_with_missing_name(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array( 'taxonomy' => 'category' );
		$result = CreateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'missing_name', $result['error']['code'] );
	}

	/**
	 * Test term creation with invalid parent.
	 */
	public function test_term_creation_with_invalid_parent(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input  = array(
			'taxonomy' => 'category',
			'name'     => 'Child Category',
			'parent'   => 999999,
		);
		$result = CreateTerm::execute( $input );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_parent', $result['error']['code'] );
	}

	/**
	 * Test successful term creation.
	 */
	public function test_successful_term_creation(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$parent_id = $this->create_test_term( 'Parent Category', 'category' );

		$input = array(
			'taxonomy'    => 'category',
			'name'        => 'Test Category',
			'slug'        => 'test-category',
			'description' => 'Test description',
			'parent'      => $parent_id,
		);

		$result = CreateTerm::execute( $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );

		$term = get_term( $result['id'], 'category' );
		$this->assertNotNull( $term );
		$this->assertSame( 'Test Category', $term->name );
		$this->assertSame( 'test-category', $term->slug );
		$this->assertSame( 'Test description', $term->description );
		$this->assertSame( $parent_id, (int) $term->parent );
	}

	/**
	 * Test ability execution through WordPress Abilities API.
	 */
	public function test_ability_execution_through_abilities_api(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $user_id );

		$input = array(
			'taxonomy' => 'category',
			'name'     => 'API Term',
		);

		$result = $this->execute_ability( 'wpmcp-example/create-term', $input );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'id', $result );
		$this->assertNotNull( get_term( $result['id'], 'category' ) );
	}
}
