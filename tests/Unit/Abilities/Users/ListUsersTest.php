<?php
/**
 * Unit tests for ListUsers ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\ListUsers;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListUsers ability functionality.
 */
final class ListUsersTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/list-users' );
	}

	/**
	 * Test permission check for admins.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( ListUsers::check_permission( array() ) );
	}

	/**
	 * Test permission check for subscribers.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( ListUsers::check_permission( array() ) );
	}

	/**
	 * Test listing users.
	 */
	public function test_execute_returns_users(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		$this->factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = ListUsers::execute( array( 'limit' => 5 ) );

		$this->assertArrayHasKey( 'users', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertNotEmpty( $result['users'] );
	}
}
