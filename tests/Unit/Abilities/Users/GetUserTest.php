<?php
/**
 * Unit tests for GetUser ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\GetUser;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetUser ability functionality.
 */
final class GetUserTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/get-user' );
	}

	/**
	 * Test permission for self profile.
	 */
	public function test_permission_check_allows_self(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( GetUser::check_permission( array( 'id' => $user_id ) ) );
	}

	/**
	 * Test permission for non-admin viewing others.
	 */
	public function test_permission_check_denies_other_users(): void {
		$user_id  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		$other_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( GetUser::check_permission( array( 'id' => $other_id ) ) );
	}

	/**
	 * Test retrieving user data.
	 */
	public function test_execute_returns_user_data(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$login   = uniqid( 'user_', false );
		$email   = $login . '@example.com';
		$user_id = $this->factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => $login,
				'user_email'   => $email,
				'display_name' => 'Sample User',
			)
		);
		wp_set_current_user( $admin_id );

		$result = GetUser::execute( array( 'id' => $user_id ) );

		$this->assertSame( $user_id, $result['id'] );
		$this->assertSame( $login, $result['login'] );
		$this->assertSame( $email, $result['email'] );
		$this->assertSame( 'Sample User', $result['display_name'] );
	}

	/**
	 * Test user not found error.
	 */
	public function test_execute_returns_error_for_missing_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$result = GetUser::execute( array( 'id' => 999999 ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'user_not_found', $result['error']['code'] );
	}
}
