<?php
/**
 * Unit tests for CreateUser ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Users;

use OvidiuGalatan\McpAdapterExample\Abilities\Users\CreateUser;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test CreateUser ability functionality.
 */
final class CreateUserTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/create-user' );
	}

	/**
	 * Test permission check for admins.
	 */
	public function test_permission_check_with_valid_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( CreateUser::check_permission( array() ) );
	}

	/**
	 * Test permission check for subscribers.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertFalse( CreateUser::check_permission( array() ) );
	}

	/**
	 * Test successful user creation.
	 */
	public function test_execute_creates_user(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$login = uniqid( 'user_', false );
		$email = $login . '@example.com';

		$result = CreateUser::execute(
			array(
				'login'             => $login,
				'email'             => $email,
				'role'              => 'subscriber',
				'display_name'      => 'Test User',
				'send_notification' => false,
			)
		);

		$this->assertArrayHasKey( 'id', $result );
		$this->assertSame( $login, $result['login'] );
		$this->assertSame( $email, $result['email'] );
		$this->assertSame( 'subscriber', $result['role'] );

		$created_user = get_user_by( 'ID', $result['id'] );
		$this->assertNotFalse( $created_user );
	}

	/**
	 * Test invalid email error.
	 */
	public function test_execute_returns_error_for_invalid_email(): void {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$result = CreateUser::execute(
			array(
				'login' => 'testuser',
				'email' => 'not-an-email',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'invalid_email', $result['error']['code'] );
	}
}
