<?php
/**
 * Unit tests for ChangeUserRole ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\ChangeUserRole;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ChangeUserRole ability functionality.
 */
final class ChangeUserRoleTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/change-user-role' );
	}

	/**
	 * Test permission check for admins on other users.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$has_permission = ChangeUserRole::check_permission(
			array(
				'user_id' => $user_id,
				'role'    => 'author',
			)
		);

		$this->assertTrue( $has_permission, 'Administrator should be able to change other user roles' );
	}

	/**
	 * Test permission check denies self role changes.
	 */
	public function test_permission_check_denies_self_role_change(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$has_permission = ChangeUserRole::check_permission(
			array(
				'user_id' => $admin_id,
				'role'    => 'author',
			)
		);

		$this->assertFalse( $has_permission, 'Users should not be able to change their own role' );
	}

	/**
	 * Test successful role change.
	 */
	public function test_execute_changes_user_role(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = ChangeUserRole::execute(
			array(
				'user_id' => $user_id,
				'role'    => 'author',
			)
		);

		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertSame( 'subscriber', $result['previous_role'] );
		$this->assertSame( 'author', $result['new_role'] );

		$updated_user = get_user_by( 'ID', $user_id );
		$this->assertTrue( in_array( 'author', $updated_user->roles, true ) );
	}

	/**
	 * Test invalid role error.
	 */
	public function test_execute_returns_error_for_invalid_role(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = ChangeUserRole::execute(
			array(
				'user_id' => $user_id,
				'role'    => 'not-a-role',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_role', $result['error']['code'] );
	}
}
