<?php
/**
 * Unit tests for GetStoreInfo ability.
 *
 * @package OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce
 */

declare( strict_types=1 );

namespace OvidiuGalatan\McpAdapterExample\Tests\Unit\Abilities\WooCommerce;

use OvidiuGalatan\McpAdapterExample\Abilities\WooCommerce\Configuration\GetStoreInfo;
use OvidiuGalatan\McpAdapterExample\Tests\TestCase;

/**
 * Test GetStoreInfo ability functionality.
 */
final class GetStoreInfoTest extends TestCase {

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

		$this->assertAbilityRegistered( 'woo/get-store-info' );
	}

	public function test_execute_returns_store_info_with_defaults(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		$user->add_cap( 'view_woocommerce_reports' );
		wp_set_current_user( $user_id );

		$result = GetStoreInfo::execute( array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'store_info', $result );
		$this->assertArrayHasKey( 'statistics', $result );
		$this->assertArrayHasKey( 'recent_activity', $result );
		$this->assertNotEmpty( $result['store_info']['store_name'] );
		$this->assertStringContainsString( $result['store_info']['store_name'], $result['message'] );
		$this->assertArrayHasKey( 'products', $result['statistics'] );
		$this->assertArrayHasKey( 'orders', $result['statistics'] );
		$this->assertArrayHasKey( 'customers', $result['statistics'] );
		$this->assertArrayHasKey( 'revenue', $result['statistics'] );
	}

	public function test_optional_flags_disable_sections(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$user    = new \WP_User( $user_id );
		$user->add_cap( 'manage_woocommerce' );
		$user->add_cap( 'view_woocommerce_reports' );
		wp_set_current_user( $user_id );

		$result = GetStoreInfo::execute(
			array(
				'include_stats'           => 'false',
				'include_recent_activity' => '0',
			)
		);

		$this->assertSame( array(), $result['statistics'] );
		$this->assertSame( array(), $result['recent_activity'] );
	}
}
