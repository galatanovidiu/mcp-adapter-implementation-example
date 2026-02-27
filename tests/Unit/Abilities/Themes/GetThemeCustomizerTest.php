<?php
/**
 * Unit tests for GetThemeCustomizer ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\Themes\GetThemeCustomizer;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetThemeCustomizer ability functionality.
 */
final class GetThemeCustomizerTest extends TestCase {

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
		$this->assertAbilityRegistered( 'core/get-theme-customizer' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$has_permission = GetThemeCustomizer::check_permission( array() );

		$this->assertTrue( $has_permission, 'Administrator should have permission to access theme customizer' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $user_id );

		$has_permission = GetThemeCustomizer::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to access theme customizer' );
	}

	/**
	 * Test customizer data for missing theme.
	 */
	public function test_execute_with_missing_theme(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$result = GetThemeCustomizer::execute(
			array(
				'stylesheet' => 'missing-theme-12345',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertSame( 'theme_not_found', $result['error']['code'] );
	}

	/**
	 * Test stylesheet sanitization when executing.
	 */
	public function test_execute_sanitizes_stylesheet(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$stylesheet = \get_stylesheet();

		$result = GetThemeCustomizer::execute(
			array(
				'stylesheet' => $stylesheet . " \n",
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( $stylesheet, $result['theme'] );
	}

	/**
	 * Test include flags are normalized to booleans.
	 */
	public function test_execute_normalizes_include_flags(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$stylesheet = \get_stylesheet();

		$result = GetThemeCustomizer::execute(
			array(
				'stylesheet'       => $stylesheet,
				'include_values'   => 'false',
				'include_controls' => 'false',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertSame( 0, count( $result['controls'] ) );
		$this->assertSame( 0, count( $result['settings'] ) );
	}
}
