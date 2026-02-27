<?php
/**
 * Unit tests for InstallTheme ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\Themes\InstallTheme;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test InstallTheme ability functionality.
 */
final class InstallThemeTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/install-theme' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$has_permission = InstallTheme::check_permission( array() );

		$this->assertTrue( $has_permission, 'Administrator should have permission to install themes' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $user_id );

		$has_permission = InstallTheme::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to install themes' );
	}

	/**
	 * Test install fails when theme already exists.
	 */
	public function test_execute_returns_existing_theme_error_for_url(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$stylesheet = \get_stylesheet();
		$theme_url  = 'https://example.com/' . $stylesheet . '.zip';

		$result = InstallTheme::execute(
			array(
				'theme' => $theme_url,
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'theme_already_exists', $result['error']['code'] );
	}
}
