<?php
/**
 * Unit tests for GetUserMeta ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\GetUserMeta;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetUserMeta ability functionality.
 */
final class GetUserMetaTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/get-user-meta' );
	}

	/**
	 * Test permission for viewing own meta.
	 */
	public function test_permission_check_allows_self(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( GetUserMeta::check_permission( array( 'user_id' => $user_id ) ) );
	}

	/**
	 * Test permission for viewing other users.
	 */
	public function test_permission_check_denies_other_users(): void {
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( GetUserMeta::check_permission( array( 'user_id' => $other_id ) ) );
	}

	/**
	 * Test retrieving user meta.
	 */
	public function test_execute_returns_user_meta(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $admin_id );

		update_user_meta( $user_id, 'favorite_color', 'blue' );

		$result = GetUserMeta::execute(
			array(
				'user_id'   => $user_id,
				'meta_keys' => array( 'favorite_color' ),
			)
		);

		$this->assertSame( $user_id, $result['user_id'] );
		$this->assertArrayHasKey( 'favorite_color', $result['meta'] );
		$this->assertContains( 'blue', (array) $result['meta']['favorite_color'] );
	}

	/**
	 * Test missing user error.
	 */
	public function test_execute_returns_error_for_missing_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$result = GetUserMeta::execute( array( 'user_id' => 999999 ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'user_not_found', $result['error']['code'] );
	}
}
