<?php
/**
 * Unit tests for GetStoreSettings ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreSettings;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetStoreSettings ability functionality.
 */
final class GetStoreSettingsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}
	}

	public function test_ability_is_registered(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not available.' );
		}

		$this->assertAbilityRegistered( 'woo/get-store-settings' );
	}

	public function test_invalid_category_returns_error(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user_id );

		$result = GetStoreSettings::execute(
			array(
				'category' => 'invalid',
			)
		);

		$this->assertSame( array(), $result['settings'] );
		$this->assertSame( array(), $result['store_info'] );
		$this->assertStringContainsString( 'Invalid settings category', $result['message'] );
	}

	public function test_category_filtering_with_default_coercion(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $user_id );

		$option_key    = 'woocommerce_store_city';
		$original_value = get_option( $option_key, '__mcp_missing__' );
		delete_option( $option_key );

		$result = GetStoreSettings::execute(
			array(
				'category'         => 'general',
				'include_defaults' => 'false',
			)
		);

		$this->assertArrayHasKey( 'general', $result['settings'] );
		$this->assertArrayNotHasKey( 'products', $result['settings'] );
		$this->assertNull( $result['settings']['general'][ $option_key ] );
		$this->assertStringContainsString( 'general', $result['message'] );

		if ( '__mcp_missing__' === $original_value ) {
			delete_option( $option_key );
			return;
		}

		update_option( $option_key, $original_value );
	}
}
