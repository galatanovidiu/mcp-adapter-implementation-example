<?php
/**
 * Unit tests for DeleteTheme ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\Themes\DeleteTheme;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test DeleteTheme ability functionality.
 */
final class DeleteThemeTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/delete-theme' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$has_permission = DeleteTheme::check_permission( array() );

		$this->assertTrue( $has_permission, 'Administrator should have permission to delete themes' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $user_id );

		$has_permission = DeleteTheme::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to delete themes' );
	}

	/**
	 * Test deletion with missing theme.
	 */
	public function test_execute_with_missing_theme(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$result = DeleteTheme::execute(
			array(
				'stylesheet' => 'missing-theme-12345',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'theme_not_found', $result['error']['code'] );
	}
}
