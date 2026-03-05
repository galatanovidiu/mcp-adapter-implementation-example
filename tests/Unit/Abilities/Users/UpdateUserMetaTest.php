<?php
/**
 * Unit tests for UpdateUserMeta ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\UpdateUserMeta;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test UpdateUserMeta ability functionality.
 */
final class UpdateUserMetaTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/update-user-meta' );
	}

	/**
	 * Test permission check for admins.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( UpdateUserMeta::check_permission( array( 'user_id' => $user_id ) ) );
	}

	/**
	 * Test permission check for subscribers.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( UpdateUserMeta::check_permission( array( 'user_id' => $other_id ) ) );
	}

	/**
	 * Test updating user meta.
	 */
	public function test_execute_updates_meta(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = UpdateUserMeta::execute(
			array(
				'user_id' => $user_id,
				'meta'    => array(
					'favorite_color' => 'green',
				),
			)
		);

		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertContains( 'favorite_color', $result['updated_keys'] );
		$this->assertSame( 'green', get_user_meta( $user_id, 'favorite_color', true ) );
	}

	/**
	 * Test invalid meta error.
	 */
	public function test_execute_returns_error_for_invalid_meta(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		$result = UpdateUserMeta::execute(
			array(
				'user_id' => $user_id,
				'meta'    => 'not-an-array',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_meta', $result['error']['code'] );
	}
}
