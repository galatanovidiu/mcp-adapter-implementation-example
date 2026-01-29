<?php
/**
 * Unit tests for UpdateStoreSettings ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\UpdateStoreSettings;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test UpdateStoreSettings ability functionality.
 */
final class UpdateStoreSettingsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
	}

	public function test_ability_is_registered(): void {
		$this->assertAbilityRegistered( 'wpmcp-example/update-store-settings' );
	}

	public function test_invalid_category_returns_error(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = UpdateStoreSettings::execute(
			array(
				'category' => 'invalid',
				'settings' => array(
					'woocommerce_store_city' => 'Test City',
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'invalid', $result['category'] );
		$this->assertStringContainsString( 'Invalid settings category', $result['message'] );
	}

	public function test_disallowed_setting_returns_error(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = UpdateStoreSettings::execute(
			array(
				'category' => 'general',
				'settings' => array(
					'not_allowed' => 'value',
				),
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'general', $result['category'] );
		$this->assertEmpty( $result['updated_settings'] );
		$this->assertStringContainsString( 'not allowed', $result['message'] );
	}

	public function test_successful_update_for_allowed_setting(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$option_key     = 'woocommerce_store_city';
		$original_value = get_option( $option_key );

		$result = UpdateStoreSettings::execute(
			array(
				'category' => 'general',
				'settings' => array(
					$option_key => 'Unit Test City',
				),
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'general', $result['category'] );
		$this->assertNotEmpty( $result['updated_settings'] );
		$this->assertEquals( 'Unit Test City', get_option( $option_key ) );

		if ( false === $original_value ) {
			delete_option( $option_key );
			return;
		}

		update_option( $option_key, $original_value );
	}
}
