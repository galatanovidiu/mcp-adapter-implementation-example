<?php
/**
 * Unit tests for DeleteUser ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\DeleteUser;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test DeleteUser ability functionality.
 */
final class DeleteUserTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/delete-user' );
	}

	/**
	 * Test permission check for admins.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( DeleteUser::check_permission( array( 'id' => $user_id ) ) );
	}

	/**
	 * Test permission check prevents self-deletion.
	 */
	public function test_permission_check_denies_self_deletion(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertFalse( DeleteUser::check_permission( array( 'id' => $admin_id ) ) );
	}

	/**
	 * Test successful user deletion.
	 */
	public function test_execute_deletes_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = DeleteUser::execute(
			array(
				'id'                   => $user_id,
				'force_delete_content' => true,
			)
		);

		$this->assertTrue( $result['deleted'] );
		$this->assertSame( 'deleted', $result['content_action'] );
		$this->assertFalse( get_user_by( 'ID', $user_id ) );
	}

	/**
	 * Test user not found error.
	 */
	public function test_execute_returns_error_for_missing_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$result = DeleteUser::execute( array( 'id' => 999999 ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'user_not_found', $result['error']['code'] );
	}
}
