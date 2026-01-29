<?php
/**
 * Unit tests for ListThemes ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\Themes;

use OvidiuGalatan\McpAdapterExample\Abilities\Themes\ListThemes;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test ListThemes ability functionality.
 */
final class ListThemesTest extends TestCase {

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
		$this->assertAbilityRegistered( 'wpmcp-example/list-themes' );
	}

	/**
	 * Test permission checking with valid user.
	 */
	public function test_permission_check_with_valid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );

		$has_permission = ListThemes::check_permission( array() );

		$this->assertTrue( $has_permission, 'Administrator should have permission to list themes' );
	}

	/**
	 * Test permission checking with invalid user.
	 */
	public function test_permission_check_with_invalid_user(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		\wp_set_current_user( $user_id );

		$has_permission = ListThemes::check_permission( array() );

		$this->assertFalse( $has_permission, 'Subscriber should not have permission to list themes' );
	}
}
