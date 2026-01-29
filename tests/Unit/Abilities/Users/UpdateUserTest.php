<?php
/**
 * Unit tests for UpdateUser ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\UpdateUser;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test UpdateUser ability functionality.
 */
final class UpdateUserTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/update-user' );
	}

	/**
	 * Test permission check for admins.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( UpdateUser::check_permission( array( 'id' => $user_id ) ) );
	}

	/**
	 * Test permission check for subscribers editing others.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( UpdateUser::check_permission( array( 'id' => $other_id ) ) );
	}

	/**
	 * Test updating user profile fields.
	 */
	public function test_execute_updates_user_fields(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = UpdateUser::execute(
			array(
				'id'           => $user_id,
				'display_name' => 'Updated Name',
				'send_notification' => false,
			)
		);

		$this->assertSame( $user_id, $result['id'] );
		$this->assertContains( 'display_name', $result['updated_fields'] );

		$updated_user = get_user_by( 'ID', $user_id );
		$this->assertSame( 'Updated Name', $updated_user->display_name );
	}

	/**
	 * Test missing user error.
	 */
	public function test_execute_returns_error_for_missing_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$result = UpdateUser::execute( array( 'id' => 999999 ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'user_not_found', $result['error']['code'] );
	}
}
